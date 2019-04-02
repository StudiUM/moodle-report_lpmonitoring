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

                DataTable.apply(tableSelector, false, false);

                // Initialise the functions to filter and search.
                $('input[type=radio][name=' + reportfilterName + ']').change(function() {
                    if (this.value == 'course') {
                        $(coursesSelector).show();
                        $(activitiesSelector).hide();
                    }
                    else if (this.value == 'module') {
                        $(coursesSelector).hide();
                        $(activitiesSelector).show();
                    }
                    else {
                        $(coursesSelector).show();
                        $(activitiesSelector).show();
                    }
                });

                $(searchSelector).on('input', function(e) {
                    $(tableSelector).DataTable().search( e.target.value ).draw();
                });

                // Do the search and filters according to the actual values.
                $('input[type=radio][name=' + reportfilterName + ']:checked').change();
                $(tableSelector).DataTable().search( $(searchSelector).val() ).draw();
                $(tableSelector).show();
            };

            return {
                init: function (tableSelector, reportfilterName, searchSelector, coursesSelector, activitiesSelector) {
                    return new ReportDataTable(tableSelector, reportfilterName, searchSelector,
                        coursesSelector, activitiesSelector);
                }
            };
        });
