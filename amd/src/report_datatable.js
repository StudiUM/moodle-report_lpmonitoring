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
 * Apply dataTable on HTML table for report.
 *
 * @package    report_lpmonitoring
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 */

define(['jquery', 'report_lpmonitoring/paginated_datatable'],
        function ($, DataTable) {

            /**
             * Constructor.
             *
             * @param {string} tableSelector The CSS selector used for the table.
             * @param {string} reportfilterName The name of the filter radio buttons.
             * @param {string} searchSelector The CSS selector used for the table search input.
             * @param {string} coursesSelector The CSS selector used for courses columns and cells.
             * @param {string} activitiesSelector The CSS selector used for activities columns and cells.
             */
            var ReportDataTable = function(tableSelector, reportfilterName, searchSelector, coursesSelector, activitiesSelector) {
                this.tableSelector = tableSelector;
                this.reportfilterName = reportfilterName;
                this.searchSelector = searchSelector;
                this.coursesSelector = coursesSelector;
                this.activitiesSelector = activitiesSelector;

                var self = this;

                DataTable.apply(tableSelector, false, false);

                // Initialise the functions to filter and search.
                $('input[type=radio][name=' + reportfilterName + ']').change(function() {
                    self.performSearch();
                });
                $(searchSelector).on('input', function() {
                    self.performSearch();
                });

                // Perform the search and filters according to the actual values.
                self.performSearch();
                $(tableSelector).show();
            };

            /** @var {String} The table CSS selector. */
            ReportDataTable.prototype.tableSelector = null;
            /** @var {String} The report filter name (for radio buttons). */
            ReportDataTable.prototype.reportfilterName = null;
            /** @var {String} The search input CSS selector. */
            ReportDataTable.prototype.searchSelector = null;
            /** @var {String} The courses cells CSS selector. */
            ReportDataTable.prototype.coursesSelector = null;
            /** @var {String} The activities (course modules) cells CSS selector. */
            ReportDataTable.prototype.activitiesSelector = null;

            /**
             * Perform the search and make sure the correct radio button is applied to the table (hide cells accordingly).
             *
             * @name   performSearch
             * @return {Void}
             * @function
             */
            ReportDataTable.prototype.performSearch = function() {
                // The search must be before the hiding of columns.
                $(this.tableSelector).DataTable().search( $(this.searchSelector).val() ).draw();

                var checkedvalue = $('input[type=radio][name=' + this.reportfilterName + ']:checked').val();
                if (checkedvalue == 'course') {
                    $(this.coursesSelector).show();
                    $(this.activitiesSelector).hide();
                }
                else if (checkedvalue == 'module') {
                    $(this.coursesSelector).hide();
                    $(this.activitiesSelector).show();
                }
                else {
                    $(this.coursesSelector).show();
                    $(this.activitiesSelector).show();
                }
            };

            return {
                init: function (tableSelector, reportfilterName, searchSelector, coursesSelector, activitiesSelector) {
                    return new ReportDataTable(tableSelector, reportfilterName, searchSelector,
                        coursesSelector, activitiesSelector);
                }
            };
        });
