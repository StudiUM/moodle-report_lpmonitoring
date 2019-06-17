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
 * Module to show a popup to view or add comments to a learning plan.
 *
 * @package    report_lpmonitoring
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2019 Université de Montréal
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
         * Constructor.
         *
         * @param {String} selector_button The CSS selector used to find triggers for the new dialogue.
         * @param {string} selector_nbcomments The CSS selector used to display the new number of comments for the plan.
         * @param {int} planid The learning plan id.
         *
         * Each call to init gets it's own instance of this class.
         */
        var CommentsPopup = function(selector_button, selector_nbcomments, planid) {
            var self = this;
            self.planid = planid;
            self.selector_nbcomments = selector_nbcomments;

            $(selector_button).on('click', this.handleClick.bind(this));
        };

        /**
         * @var {int} planid
         * @private
         */
        CommentsPopup.prototype.planid = -1;

        /**
         * @var {string} selector_nbcomments  The CSS selector used to display the new number of comments for the plan.
         * @private
         */
        CommentsPopup.prototype.selector_nbcomments = '';

        /**
         * @var {string} selector_commentlist  The CSS selector for the comment list.
         * @private
         */
        CommentsPopup.prototype.selector_commentlist = ".moodle-dialogue-wrap .comment-list";

        /**
         * @var {Dialogue} popup  The popup window (Dialogue).
         * @private
         */
        CommentsPopup.prototype.popup = null;

        /**
         * @var float actual_size  The size of the comment area.
         * @private
         */
        CommentsPopup.prototype.actual_size = 0;

        /**
         * Get the data from the clicked cell and open the popup.
         *
         * @method _handleClick
         * @param {Event} e
         */
        CommentsPopup.prototype.handleClick = function(e) {
            e.preventDefault();
            var self = this;
            ajax.call([{
                methodname : 'report_lpmonitoring_get_comment_area_for_plan',
                args: { planid: self.planid },
                done: self.commentareaLoaded.bind(self),
                fail: notification.exception
            }]);
        };

        /**
         * We loaded the commentarea, now render the template.
         *
         * @method commentareaLoaded
         * @param {Object} commentarea
         */
        CommentsPopup.prototype.commentareaLoaded = function(commentarea) {
            var self = this;
            // We have to display user info in popup.
            templates.render('report_lpmonitoring/comment_area', commentarea).done(function(html, js) {
                str.get_string('commentsedit', 'report_lpmonitoring').done(function(title) {
                    self.popup = new Dialogue(title, html, self.open.bind(self, js), self.close.bind(self), true);
                    $("body").on('DOMSubtreeModified', self.selector_commentlist, self.checkPopupSize.bind(self));
                }).fail(notification.exception);
            }).fail(notification.exception);
        };

        /**
         * Open the popup.
         *
         * @method open
         */
        CommentsPopup.prototype.open = function(js) {
            templates.runTemplateJS(js);
        };

        /**
         * Close the popup and update comment count.
         *
         * @method close
         */
        CommentsPopup.prototype.close = function() {
            // Update the comment count.
            var self = this;
            var requests = ajax.call([{
                methodname : 'report_lpmonitoring_get_comment_area_for_plan',
                args: { planid: self.planid },
                fail: notification.exception
            }]);

            requests[0].then(function (commentarea) {
                $(self.selector_nbcomments).text(commentarea.count);
            });

            // Destroy the popup.
            $("body").off('DOMSubtreeModified', self.selector_commentlist);
            self.popup.close();
            self.popup = null;
        };

        /**
         * Checks if all comments can be seen in the popup, and if not, show the popup full screen.
         *
         * @method checkPopupSize
         */
        CommentsPopup.prototype.checkPopupSize = function() {
            var self = this;

            var newSize = $(self.selector_commentlist).height();
            // If the height of the comment area has changed and is bigger than before.
            if( newSize > self.actual_size ) {
                var bb = self.popup.yuiDialogue.get('boundingBox');

                // If the comments cannot be completly seen in the window, show fullscreen.
                if( $('.moodle-dialogue').height() > bb.get('winHeight')) {
                    bb.addClass('moodle-dialogue-fullscreen');

                    bb.setStyles({'left': null,
                        'top': null,
                        'width': null,
                        'height': null,
                        'right': null,
                        'bottom': null});
                }
            }
            self.actual_size = newSize;
        };

        return {
            /**
             * Attach event listeners to initialise this module.
             *
             * @method init
             * @param {string} selector_button The CSS selector used to find nodes that will trigger this module.
             * @param {string} selector_nbcomments The CSS selector used to display the new number of comments for the plan.
             * @param {int} planid The learning plan id.
             * @return {CommentsPopup} A new instance of CommentsPopup.
             */
            init: function(selector_button, selector_nbcomments, planid) {
                return new CommentsPopup(selector_button, selector_nbcomments, planid);
            }
        };
    });