<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Test fixture that deterministically simulates the concurrent INSERT race window
 * on the tool_cmcompetency_usercompcm unique index (usecmicom_uix).
 *
 * A real multi-worker race cannot be reproduced inside a single PHPUnit process.
 * This database decorator wraps the live $DB and forces the exact failure mode of
 * the race: when the persistent layer attempts to INSERT a user_competency_coursemodule
 * row for the targeted (userid, cmid, competencyid) triplet - having just read it as
 * absent - the decorator first inserts the row itself (mimicking the concurrent
 * request that won the race) and then throws the same dml_write_exception a real
 * duplicate-key violation would raise. This exercises the unprotected COURSE MODULE
 * call path in report_lpmonitoring\api::get_competency_detail() without modifying
 * tool_cmcompetency.
 *
 * The decorator is a pure delegator: every operation except the poisoned insert is
 * forwarded to the real database via __call, so it stays driver agnostic and never
 * runs an inherited moodle_database method against an unconnected instance.
 *
 * @package    report_lpmonitoring
 * @copyright  2024 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Database decorator that injects a duplicate-key failure on a target table.
 *
 * @package    report_lpmonitoring
 * @copyright  2024 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_lpmonitoring_race_condition_database {
    /** @var \moodle_database The real database this decorator delegates to. */
    protected $realdb;

    /** @var string The table on which the race is simulated. */
    protected $racetable = 'tool_cmcompetency_usercompcm';

    /** @var bool When true, the concurrent row is inserted before throwing (recoverable). */
    protected $insertconcurrentrow = true;

    /** @var bool When true, the duplicate-key failure is armed. */
    protected $armed = true;

    /** @var int Number of times the race was triggered. */
    public $racetriggered = 0;

    /**
     * @var int|null When set, only the Nth INSERT attempt on the race table is poisoned
     * (1-based). This targets a call site by its ordinal position in the execution flow
     * rather than by a hardcoded file:line, so it stays robust across refactors of
     * report_lpmonitoring\api (e.g. extracting a shared helper).
     */
    protected $targetinsertindex = null;

    /** @var int Number of INSERT attempts observed on the race table so far. */
    protected $insertattempts = 0;

    /**
     * @var bool When true, inserts on the race table that are NOT at the target ordinal are
     * performed and then immediately removed, keeping the triplet "absent" so a later
     * call site (e.g. the cms loop) still attempts its own INSERT. This lets us isolate
     * the second COURSE MODULE call site even though the modules loop runs first.
     */
    protected $suppressnontargetinserts = false;

    /**
     * Build the decorator around the live database instance.
     *
     * @param \moodle_database $realdb The real database.
     * @param bool $insertconcurrentrow Whether to insert the row before throwing (recoverable race).
     * @param string|null $racetable Optional target table (defaults to the usercompcm table).
     */
    public function __construct(\moodle_database $realdb, bool $insertconcurrentrow = true, ?string $racetable = null) {
        $this->realdb = $realdb;
        $this->insertconcurrentrow = $insertconcurrentrow;
        if ($racetable !== null) {
            $this->racetable = $racetable;
        }
    }

    /**
     * Poison the Nth INSERT attempt on the race table (1-based ordinal).
     *
     * The two COURSE MODULE call sites of get_competency_detail() (the modules loop and the
     * cms loop) share the same table and now the same private helper, so neither file:line nor
     * the immediate caller method distinguishes them. What DOES distinguish them reliably is
     * execution order: in the normal flow the modules loop runs first and would create the
     * record, so the cms loop never inserts. We therefore target call sites by the ordinal of
     * their INSERT attempt, which is robust to any refactor of the api class:
     *  - modules loop  => target the 1st INSERT (target_nth_insert(1)).
     *  - cms loop       => suppress the modules-loop INSERT (perform then remove, keeping the
     *                      triplet absent) and target the 2nd INSERT
     *                      (target_nth_insert(2, true)).
     *
     * @param int $n The 1-based ordinal of the INSERT attempt on the race table to poison.
     * @param bool $suppressnontargetinserts When true, non-target inserts on the race table are
     *        performed then removed, so the triplet stays absent for the later call site.
     */
    public function target_nth_insert(int $n, bool $suppressnontargetinserts = false): void {
        $this->targetinsertindex = $n;
        $this->suppressnontargetinserts = $suppressnontargetinserts;
    }

    /**
     * Whether the current INSERT attempt is the configured target ordinal.
     *
     * When no ordinal is configured every INSERT on the race table is a target, preserving the
     * "fail on any insert" behaviour relied on by the existing-record and COURSE-case tests.
     *
     * @param int $attempt The 1-based ordinal of the current INSERT attempt on the race table.
     * @return bool
     */
    protected function is_target_insert(int $attempt): bool {
        if ($this->targetinsertindex === null) {
            return true;
        }
        return $attempt === $this->targetinsertindex;
    }

    /**
     * Disarm the simulated failure (so cleanup / teardown can run against the real DB).
     */
    public function disarm(): void {
        $this->armed = false;
    }

    /**
     * Return the wrapped real database instance.
     *
     * @return \moodle_database
     */
    public function get_real_database(): \moodle_database {
        return $this->realdb;
    }

    /**
     * Delegate every non-overridden database operation to the real database.
     *
     * @param string $name Method name.
     * @param array $arguments Method arguments.
     * @return mixed
     */
    public function __call($name, $arguments) {
        return call_user_func_array([$this->realdb, $name], $arguments);
    }

    /**
     * Delegate static calls to the real database class.
     *
     * @param string $name Method name.
     * @param array $arguments Method arguments.
     * @return mixed
     */
    public static function __callStatic($name, $arguments) {
        return call_user_func_array(['moodle_database', $name], $arguments);
    }

    /**
     * Intercept inserts into the target table to simulate the unique-key race.
     *
     * @param string $table The table name.
     * @param object|array $dataobject The record to insert.
     * @param bool $returnid Whether to return the new id.
     * @param bool $bulk Bulk insert flag.
     * @return bool|int
     */
    public function insert_record($table, $dataobject, $returnid = true, $bulk = false) {
        if ($this->armed && $table === $this->racetable) {
            $this->insertattempts++;
            if ($this->is_target_insert($this->insertattempts)) {
                $this->racetriggered++;
                $data = (array) $dataobject;
                $userid = $data['userid'] ?? 0;
                $cmid = $data['cmid'] ?? 0;
                $competencyid = $data['competencyid'] ?? 0;

                if ($this->insertconcurrentrow) {
                    // A concurrent request won the race and created the relation just now.
                    $this->realdb->insert_record($table, $dataobject, false, $bulk);
                }

                // The losing INSERT violates the unique index and raises dml_write_exception,
                // exactly as observed in production.
                $key = 'mdl_tool_cmcompetency_usercompcm.mdl_toolcmcouser_usecmicom_uix';
                $message = "Duplicate entry '{$userid}-{$cmid}-{$competencyid}' for key '{$key}'";
                throw new \dml_write_exception(
                    $message,
                    "INSERT INTO {{$table}} ...",
                    $data
                );
            }

            if ($this->suppressnontargetinserts) {
                // Perform the insert then remove it so the triplet stays "absent" for the
                // later target call site. This keeps the modules loop from shadowing the
                // cms loop, allowing the second COURSE MODULE call site to be exercised.
                $data = (array) $dataobject;
                $id = $this->realdb->insert_record($table, $dataobject, true, $bulk);
                $this->realdb->delete_records($table, ['id' => $id]);
                return $returnid ? $id : true;
            }
        }

        return $this->realdb->insert_record($table, $dataobject, $returnid, $bulk);
    }
}
