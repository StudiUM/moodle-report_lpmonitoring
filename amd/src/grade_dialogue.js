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
 * Grade dialogue override for report_lpmonitoring.
 *
 * This module extends tool_lp/grade_dialogue to fix a timing issue where
 * Templates.runTemplateJS() was called before the YUI Dialogue had rendered
 * its content into the DOM. This caused core_form/changechecker watchFormById
 * and editor_tiny/editor setupForTarget to fail with "Cannot read properties
 * of null" because document.getElementById() returned null.
 *
 * The fix defers runTemplateJS to the _afterRender callback, which fires
 * only after the YUI Dialogue is shown and its body content is in the DOM.
 *
 * @module     report_lpmonitoring/grade_dialogue
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @copyright  2026 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery',
        'core/notification',
        'core/templates',
        'tool_lp/dialogue',
        'tool_lp/event_base',
        'core/str',
        'core/fragment',
        'core/config',
        'core_form/events',
        'core_form/changechecker'],
        function($, Notification, Templates, Dialogue, EventBase, Str, Fragment, Config, FormEvents, FormChangeChecker) {

    /**
     * Grade dialogue class.
     *
     * @class report_lpmonitoring/grade_dialogue
     * @param {Array} ratingOptions
     */
    var Grade = function(ratingOptions) {
        EventBase.prototype.constructor.apply(this, []);
        this._ratingOptions = ratingOptions;
    };
    Grade.prototype = Object.create(EventBase.prototype);

    /** @property {Dialogue} The dialogue. */
    Grade.prototype._popup = null;
    /** @property {Array} Array of objects containing, 'value', 'name' and optionally 'selected'. */
    Grade.prototype._ratingOptions = null;
    /** @property {String} Pending template JS to run after the dialogue is shown. */
    Grade.prototype._pendingJS = null;

    /**
     * After render hook.
     *
     * @method _afterRender
     * @protected
     */
    Grade.prototype._afterRender = function() {
        var btnRate = this._find('[data-action="rate"]'),
            lstRating = this._find('[name="rating"]'),
            txtComment = this._find('[name="comment"]');

        // Run the template JS now that the dialogue content is in the DOM.
        // This ensures that core_form/changechecker watchFormById and editor_tiny/editor
        // setupForTarget can find their target elements via document.getElementById.
        if (this._pendingJS) {
            Templates.runTemplateJS(this._pendingJS);
            this._pendingJS = null;
        }

        // Disable form change checker on the grader form.
        // This form is a disposable rating dialogue — the "unsaved changes" warning is not needed
        // and watchFormById causes errors when the form is loaded in an AJAX/fragment context.
        var graderForm = this._find('form');
        if (graderForm.length) {
            FormChangeChecker.unWatchForm(graderForm[0]);
        }

        this._find('[data-action="cancel"]').click(function(e) {
            e.preventDefault();
            this._trigger('cancelled');
            this.close();
        }.bind(this));

        lstRating.change(function() {
            var node = $(this);
            if (!node.val()) {
                btnRate.prop('disabled', true);
            } else {
                btnRate.prop('disabled', false);
            }
        }).change();

        btnRate.click(function(e) {
            e.preventDefault();
            var val = lstRating.val();
            if (!val) {
                return;
            }
            this._trigger('rated', {
                'rating': val,
                'note': txtComment.val()
            });
            // Catch the submit event to remove autosave session.
            FormEvents.notifyFormSubmittedByJavascript(this._find('form')[0]);
            this.close();
        }.bind(this));
    };

    /**
     * Close the dialogue.
     *
     * @method close
     */
    Grade.prototype.close = function() {
        if (this._popup) {
            this._popup.close();
            this._popup = null;
        }
    };

    /**
     * Opens the picker.
     *
     * The key difference from tool_lp/grade_dialogue: Templates.runTemplateJS
     * is NOT called here. Instead the JS is stored in _pendingJS and executed
     * in _afterRender, when the dialogue content is guaranteed to be in the DOM.
     *
     * @method display
     * @return {Promise}
     */
    Grade.prototype.display = function() {
        return $.when(
            Str.get_string('rate', 'tool_lp'),
            this._render()
        )
        .then(function(title, templateResult) {
            // Store the JS to run after the dialogue is shown and its content is in the DOM.
            this._pendingJS = templateResult[1];
            this._popup = new Dialogue(
                title,
                templateResult[0],
                this._afterRender.bind(this),
                this.close.bind(this),
                true
            );

            return this._popup;
        }.bind(this))
        .catch(Notification.exception);
    };

    /**
     * Find a node in the dialogue.
     *
     * @param {String} selector
     * @method _find
     * @returns {node} The node
     * @protected
     */
    Grade.prototype._find = function(selector) {
        return $(this._popup.getContent()).find(selector);
    };

    /**
     * Render the dialogue.
     *
     * @method _render
     * @protected
     * @return {Promise}
     */
    Grade.prototype._render = function() {
        var args = {};
        args.canGrade = (this._canGrade) ? true : false;
        args.ratingOptions = JSON.stringify(this._ratingOptions);
        args.contextid = Config.contextid;

        return Fragment.loadFragment('tool_lp', 'competency_grader', Config.contextid, args);
    };

    return Grade;
});
