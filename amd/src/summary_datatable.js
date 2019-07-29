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
 * Apply dataTable on HTML table for summary.
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
            var SummaryDataTable = function(tableSelector, reportfilterName, searchSelector, totalSelector, coursesSelector,
            activitiesSelector) {
                this.tableSelector = tableSelector;
                this.reportfilterName = reportfilterName;
                this.searchSelector = searchSelector;
                this.totalSelector = totalSelector;
                this.coursesSelector = coursesSelector;
                this.activitiesSelector = activitiesSelector;
                /* TODO EVOSTDM-1879 : Voir si nécessaire de garder columns
                this.columns = [];
                */

                var self = this;
                // Perform the search and filters according to the actual values.
                $(document).ready(function() {
                    DataTable.apply(self.tableSelector, false, false);

                    $(self.searchSelector).on('input', function() {
                        self.performSearch();
                    });
                    // TODO EVOSTDM-1879 : Filtre par cours/activité/total
                    $('input[type=radio][name=' + self.reportfilterName + ']').change(function() {
                        self.courseActivityFilter();
                        self.performSearch();
                    });

                    $(tableSelector).show();
                    /* TODO EVOSTDM-1879
                    self.courseActivityFilter();
                    */
                    self.performSearch();
                });
            };

            /** @var {String} The table CSS selector. */
            SummaryDataTable.prototype.tableSelector = null;
            /** @var {String} The report filter name (for radio buttons). */
            SummaryDataTable.prototype.reportfilterName = null;
            /** @var {String} The search input CSS selector. */
            SummaryDataTable.prototype.searchSelector = null;
            /** @var {String} The total CSS selector. */
            SummaryDataTable.prototype.totalSelector = null;
            /** @var {String} The courses cells CSS selector. */
            SummaryDataTable.prototype.coursesSelector = null;
            /** @var {String} The activities (course modules) cells CSS selector. */
            SummaryDataTable.prototype.activitiesSelector = null;
            /** @var {Array} The columns indexes. */
            SummaryDataTable.prototype.columns = [];

            /**
             * Perform the search in competency names (hide rows accordingly).
             *
             * @name   performSearch
             * @return {Void}
             * @function
             */
            SummaryDataTable.prototype.performSearch = function() {
                $(this.tableSelector).DataTable().column(0).search($(this.searchSelector).val(), false, false).draw();
            };

            /**
             * Switch display between course and activity.
             *
             * @name   courseActivityFilter
             * @return {Void}
             * @function
             */
            SummaryDataTable.prototype.courseActivityFilter = function() {
                // TODO EVOSTDM-1879 : Filtre par cours/activité/total
                /*
                var self = this;
                var checkedvalue = $('input[type=radio][name=' + self.reportfilterName + ']:checked').val();
                var classcolumn = '';
                var courseormodule = false;
                if (checkedvalue === 'course') {
                    classcolumn = 'course-cell';
                    courseormodule = true;
                }
                else if (checkedvalue === 'module') {
                    classcolumn = 'cm-cell';
                    courseormodule = true;
                }
                if (courseormodule) {
                    $(self.tableSelector + " thead tr th").each(function( index ) {
                        if (index > 1) {
                            var column = $(self.tableSelector).DataTable().column(index);
                            var columnheader = column.header();
                            var condition = $(this).hasClass(classcolumn);
                            $(columnheader).toggleClass('switchsearchhidden', !condition);
                            $(this).toggleClass('switchsearchhidden', !condition);
                            column.nodes().to$().toggleClass('switchsearchhidden', !condition);
                        }
                    });
                } else {
                    $.each(self.columns, function(index, value) {
                        var column = $(self.tableSelector).DataTable().column(value);
                        var columnheader = column.header();
                        $(columnheader).removeClass('switchsearchhidden');
                        column.nodes().to$().removeClass('switchsearchhidden');
                    });
                }
                */
            };

            return {
                init: function (tableSelector, reportfilterName, searchSelector, totalSelector, coursesSelector,
                    activitiesSelector) {
                        return new SummaryDataTable(tableSelector, reportfilterName, searchSelector, totalSelector, coursesSelector,
                            activitiesSelector);
                }
            };
        });
