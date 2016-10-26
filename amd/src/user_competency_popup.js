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
 * Module to enable inline editing of a comptency grade.
 *
 * @package    report_lpmonitoring
 * @author     Jean-Philippe Gaudreau <jp.gaudreau@umontreal.ca>
 * @copyright  2016 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery',
        'core/notification',
        'core/str',
        'core/ajax',
        'core/templates',
        'tool_lp/dialogue'],
    function($, notification, str, ajax, templates, Dialogue) {

        /**
         * UserCompetencyPopup
         *
         * @param {String} The regionSelector
         * @param {String} The userCompetencySelector
         */
        var UserCompetencyPopup = function(regionSelector, userCompetencySelector) {
            this._regionSelector = regionSelector;
            this._userCompetencySelector = userCompetencySelector;
            this._competencyId = null;
            this._planId = null;
            this._userId = null;

            $(this._regionSelector).on('click', this._userCompetencySelector, this._handleClick.bind(this));
        };

        /**
         * Get the data from the clicked cell and open the popup.
         *
         * @method _handleClick
         * @param {Event} e
         */
        UserCompetencyPopup.prototype._handleClick = function(e) {
            e.preventDefault();
            var self = this;
            var cell = $(e.target).closest(this._userCompetencySelector);
            self._competencyId = $(cell).data('competencyid');
            self._planId = $(cell).data('planid');
            self._userId = $(cell).data('userid');

            var requests = ajax.call([{
                methodname : 'tool_lp_data_for_user_competency_summary_in_plan',
                args: { competencyid: self._competencyId , planid: self._planId },
                done: self._contextLoaded.bind(self),
                fail: notification.exception
            }]);

            // Log the user competency viewed in plan event.
            requests[0].then(function (result) {
                var eventMethodName = 'core_competency_user_competency_viewed_in_plan';
                // Trigger core_competency_user_competency_plan_viewed event instead if plan is already completed.
                if (result.plan.iscompleted) {
                    eventMethodName = 'core_competency_user_competency_plan_viewed';
                }
                ajax.call([{
                    methodname: eventMethodName,
                    args: {competencyid: self._competencyId, userid: self._userId, planid: self._planId},
                    fail: notification.exception
                }]);
            });
        };

        /**
         * We loaded the context, now render the template.
         *
         * @method _contextLoaded
         * @param {Object} context
         */
        UserCompetencyPopup.prototype._contextLoaded = function(context) {
            var self = this;
            // We have to display user info in popup.
            templates.render('tool_lp/user_competency_summary_in_plan', context).done(function(html, js) {
                str.get_string('usercompetencysummary', 'report_competency').done(function(title) {
                    (new Dialogue(title, html, templates.runTemplateJS.bind(templates, js), self._refresh.bind(self), true));
                }).fail(notification.exception);
            }).fail(notification.exception);
        };

        /**
         * Refresh the page.
         *
         * @method _refresh
         */
        UserCompetencyPopup.prototype._refresh = function() {};

        return /** @alias module:tool_lp/configurecoursecompetencysettings */ UserCompetencyPopup;
    });
