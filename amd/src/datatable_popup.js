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
 * Popup to view detail of competency for a user, for a course or course module.
 *
 * @package    report_lpmonitoring
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or laterer
 */

define(['jquery', 'core/notification', 'core/str', 'core/ajax', 'core/templates', 'tool_lp/dialogue'],
        function($, notification, str, ajax, templates, Dialogue) {

            /**
             * DatatablePopup
             *
             * @param {String} regionSelector The regionSelector
             * @param {String} userCompetencySelector The userCompetencySelector
             */
            var DatatablePopup = function(regionSelector, userCompetencySelector) {
                $(regionSelector).on('click', userCompetencySelector, this._handleClick.bind(this));
            };

            /**
             * Get the data from the clicked cell and open the popup.
             *
             * @method _handleClick
             * @param {Event} e The event
             */
            DatatablePopup.prototype._handleClick = function(e) {
                // Do not scroll to top.
                e.preventDefault();

                var cell = $(e.target);
                var competencyId = $(cell).data('competencyid');
                var elementId = $(cell).data('elementid');
                var userId = $(cell).data('userid');
                var type = $(cell).data('type');

                if (type == 'cm') {
                    var requests = ajax.call([{
                        methodname: 'tool_cmcompetency_data_for_user_competency_summary_in_coursemodule',
                        args: {userid: userId, competencyid: competencyId, cmid: elementId},
                    }, {
                        methodname: 'tool_cmcompetency_user_competency_viewed_in_coursemodule',
                        args: {userid: userId, competencyid: competencyId, cmid: elementId},
                    }]);
                } else {
                    var requests = ajax.call([{
                        methodname: 'report_lpmonitoring_data_for_user_competency_summary_in_course',
                        args: {userid: userId, competencyid: competencyId, courseid: elementId},
                    }, {
                        methodname: 'report_lpmonitoring_user_competency_viewed_in_course',
                        args: {userid: userId, competencyid: competencyId, courseid: elementId},
                    }]);
                }

                $.when.apply($, requests).then(function(context) {
                    this._contextLoaded.bind(this)(context);
                    return;
                }.bind(this)).catch(notification.exception);
            };

            /**
             * We loaded the context, now render the template.
             *
             * @method _contextLoaded
             * @param {Object} context
             */
            DatatablePopup.prototype._contextLoaded = function(context) {
                var self = this;
                // We have to display user info in popup.
                context.displayuser = true;
                // Impossible to grade course directly in this popup.
                context.usercompetencysummary.cangrade = false;

                var templatepath = 'tool_lp/user_competency_summary_in_course';
                if (typeof context.coursemodule !== 'undefined') {
                    templatepath = 'report_cmcompetency/user_competency_summary_in_coursemodule';
                }
                templates.render(templatepath, context).done(function(html, js) {
                    str.get_string('usercompetencysummary', 'report_competency').done(function(title) {
                        (new Dialogue(title, html, templates.runTemplateJS.bind(templates, js), self.destroyDialogue, true));
                    }).fail(notification.exception);
                }).fail(notification.exception);
            };

            /**
             * Destroy DOM after close.
             *
             * @param Dialogue
             * @function
             */
            DatatablePopup.prototype.destroyDialogue = function(dialg) {
                dialg.close();
            };

            return DatatablePopup;

        });
