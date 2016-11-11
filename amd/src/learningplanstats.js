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
 * Learning plan stats.
 *
 * @package    report_lpmonitoring
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 */

define(['jquery',
    'core/templates',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/chartjs',
    'core/form-autocomplete',
    'report_lpmonitoring/fieldsettoggler',
    'report_lpmonitoring/colorcontrast',
    'tool_lp/dialogue',
    'report_lpmonitoring/paginated_datatable'],
    function($, templates, ajax, notification, str, Chart, autocomplete, Toggler,
            colorcontrast, Dialogue, DataTable) {

        /**
         * Learning plan stats.
         */
        var LearningplanStats = function() {

            // Init the form filter.
            this.initPage();

            // Init the color contrast object.
            this.colorContrast = colorcontrast.init();

            // Template change Handler.
            $(this.templateSelector).on('change', this.templateChangeHandler.bind(this)).change();
        };

        /** @var {String} The template select box selector. */
        LearningplanStats.prototype.templateSelector = "#templateSelectorStats";
        /** @var {String} The template ID. */
        LearningplanStats.prototype.templateId = null;
        /** @var {Array} Competencies informations. */
        LearningplanStats.prototype.competencies = {};
        /** @var {ColorContrast} ColorContrast object instance. */
        LearningplanStats.prototype.colorcontrast = null;

        /**
         * Triggered when a template is selected.
         *
         * @name   templateChangeHandler
         * @param  {Event} e
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.templateChangeHandler = function(e) {
            var self = this;
            self.templateId = $(e.target).val();

            if (self.templateId) {
                $('#submitFilterStatstButton').removeAttr('disabled');
            } else {
                $('#submitFilterStatstButton').attr('disabled', 'disabled');
            }
        };

        /**
         * Submit filter form.
         *
         * @name   submitFormHandler
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.submitFormHandler = function() {
            var self = this;
            if (self.templateId) {
                self.loadListCompetencies(self.templateId);
            }
        };

        /**
         * Load list of competencies of a specified template.
         *
         * @name   loadListCompetencies
         * @param  {Number} templateid
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.loadListCompetencies = function(templateid) {
            var self = this,
                ratingincourse = true;

            if ($("#ratinginplanoption").is(':checked')) {
                ratingincourse = false;
            }

            var promiselistCompetencies = ajax.call([{
                methodname: 'core_competency_list_competencies_in_template',
                args: {
                    id: templateid
                }
            }]);
            var elementloading = $("#submitFilterStatstButton");
            elementloading.addClass('loading');
            promiselistCompetencies[0].then(function(results) {
                if (results.length > 0) {
                    var competencies = {competencies_list:results};
                    return templates.render('report_lpmonitoring/list_competencies_stats', competencies).done(function(html, js) {
                        $("#list-competencies-template").html(html);
                        templates.runTemplateJS(js);
                        elementloading.removeClass('loading');
                        self.loadCompetencyDetail(results, templateid, ratingincourse);
                    });
                } else {
                    elementloading.removeClass('loading');
                    return templates.render('report_lpmonitoring/list_competencies_stats', {}).done(function(html, js) {
                        $("#list-competencies-template").html(html);
                        templates.runTemplateJS(js);
                    });
                }
            }).fail(
                function(exp) {
                    elementloading.removeClass('loading');
                    notification.exception(exp);
                }
            );
        };

        /**
         * Load competency detail.
         *
         * @name  loadCompetencyDetail
         * @param {Object[]} competencies
         * @param {Number} templateid
         * @param {Boolean} ratingincourse
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.loadCompetencyDetail = function(competencies, templateid, ratingincourse) {
            var requests = [];
            var self = this;
            var servicename = 'report_lpmonitoring_get_competency_statistics';
            var templatename = 'report_lpmonitoring/competency_detail_stats';
            if (ratingincourse) {
                servicename = 'report_lpmonitoring_get_competency_statistics_incourse';
                templatename = 'report_lpmonitoring/competency_detail_stats_incourse';
            }

            $.each(competencies, function(index, record) {
                // Locally store competency information.
                self.competencies[record.id] = {infocompetency: record};
                requests.push({
                    methodname: servicename,
                    args: {
                        competencyid: record.id,
                        templateid: templateid
                    }
                });
            });
            $('.competencyreport .competency-detail').addClass('loading');
            $.when.apply($.when, ajax.call(requests))
            .then(function() {
                $.each(arguments, function(index, context) {
                    var compid = context.competencyid;
                    // Locally store competency statitstics.
                    self.competencies[compid].competencydetail = context;
                    templates.render(templatename, context).done(function(html, js) {
                        $('#comp-' + compid).removeClass('loading');
                        $('#comp-' + compid + ' .x_content').html(html);
                        // Apply Donut Graph to the competency.
                        var options = {
                            legend: false,
                            responsive: false,
                            tooltips: {enabled: false}
                        };
                        var colors = [];
                        var datascales = [];
                        var applygraph = false;
                        if (ratingincourse === false && context.nbuserrated !== 0) {
                            $.each(context.scalecompetencyitems, function(index, record) {
                                colors.push(record.color);
                                datascales.push(record.nbusers);
                            });
                            applygraph = true;
                        }
                        if (ratingincourse === true && context.nbratings !== 0) {
                            $.each(context.scalecompetencyitems, function(index, record) {
                                colors.push(record.color);
                                datascales.push(record.nbratings);
                            });
                            applygraph = true;
                        }
                        if (applygraph === true) {
                            new Chart($('#canvas-graph-' + compid), {
                                type: 'doughnut',
                                data: {
                                    labels: [],
                                    datasets: [{
                                        data: datascales,
                                        backgroundColor: colors,
                                        hoverBackgroundColor: []
                                    }]
                                },
                                options: options
                            });
                        }
                        templates.runTemplateJS(js);
                        self.colorContrast.apply('#comp-' + compid + ' .x_content .tile-stats .label.cr-scalename');
                    });
                });
            }).fail(function(ex) {
                $("#list-competencies-template").empty();
                notification.exception(ex);
            });
        };

        /**
         * Display the list of users in competency.
         *
         * @name   displayScaleUserList
         * @param  {Array} listusers
         * @param  {Number} competencyid
         * @param  {Number} scalevalue
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.displayScaleUserList = function(listusers, competencyid, scalevalue) {
            var self = this;
            listusers.competencyid = competencyid;
            listusers.scalevalue = scalevalue;
            if (listusers.scalecompetencyitem.listusers.length > 0) {
                str.get_string('linkedusers', 'report_lpmonitoring').done(
                function(titledialogue) {
                    templates.render('report_lpmonitoring/list_users_in_scale_value', listusers)
                        .done(function(html, js) {
                            // Show the dialogue.
                            new Dialogue(
                                titledialogue,
                                html,
                                function() {
                                    DataTable.apply('#list-user-' + competencyid + '-' + scalevalue);
                                },
                                self.destroyDialogue
                            );
                            templates.runTemplateJS(js);
                            self.colorContrast.apply('.moodle-dialogue-base .label.cr-scalename');
                        }).fail(notification.exception);
                });
            }
        };

        /**
         * Display the list of users in competency.
         *
         * @name   displayTotalUserList
         * @param  {Array} listusers
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.displayTotalUserList = function(listusers) {
            var self = this;
            if (listusers.totaluserlist.length > 0) {
                str.get_string('userlist').done(
                function(titledialogue) {
                    templates.render('report_lpmonitoring/list_users_in_competency_stats', listusers)
                        .done(function(html, js) {
                            // Show the dialogue.
                            new Dialogue(
                                titledialogue,
                                html,
                                function(){
                                    DataTable.apply('#list-users-stats-' + listusers.competencyid);
                                },
                                self.destroyDialogue
                            );
                            templates.runTemplateJS(js);
                        }).fail(notification.exception);
                });
            }
        };

        /**
         * destroy DOM after close.
         *
         * @param Dialogue
         * @function
         */
        LearningplanStats.prototype.destroyDialogue = function(dialg) {
            dialg.close();
        };

        /**
         * Init the differents page blocks and inputs form.
         *
         * @name   initPage
         * @return {Void}
         * @function
         */
        LearningplanStats.prototype.initPage = function() {
            var self = this;
            str.get_strings([
                { key: 'selectlearningplantemplate', component: 'report_lpmonitoring' },
                { key: 'notemplateselected', component: 'report_lpmonitoring' }]
            ).done(
                function (strings) {
                    // Autocomplete for templates.
                    autocomplete.enhance(
                        self.templateSelector,
                        false,
                        'report_lpmonitoring/learningplanstats',
                        strings[0],
                        false,
                        true,
                        strings[1]);
                }
            ).fail(notification.exception);

            // Allow collapse of block panels.
            Toggler.init();

            // Filter form submit.
            $(document).on('submit', '#statstFilter', function(){
                self.submitFormHandler();
                return false;
            });

            // Handle click on scale number users.
            $(".competencyreport").on('click', 'a.scaleinfo', function(event) {
                event.preventDefault();
                var competencyid = $(this).data("competencyid");
                var scalevalue = $(this).data("scalevalue");

                if (typeof self.competencies[competencyid] !== 'undefined') {
                    var listusers = {};
                    var competencydetail = self.competencies[competencyid].competencydetail;
                    listusers.scalecompetencyitem = competencydetail.scalecompetencyitems[scalevalue - 1];
                    self.displayScaleUserList(listusers, competencyid, scalevalue);
                }
            });
            // Handle click on total users.
            $(".competencyreport").on('click', 'a.totalnbusers', function(event) {
                event.preventDefault();
                var competencyid = $(this).data("competencyid");
                if (typeof self.competencies[competencyid] !== 'undefined') {
                    var users = {};
                    users.totaluserlist = self.competencies[competencyid].competencydetail.totaluserlist;
                    users.competencyid = competencyid;
                    self.displayTotalUserList(users);
                }
            });
        };

        return {
            /**
             * Main initialisation.
             *
             * @return {LearningplanStats}
             * @method init
             */
            init: function() {
                return new LearningplanStats();
            },
            /**
             * Process result autocomplete for templates.
             *
             * @param {type} selector
             * @param {type} results
             * @returns {Array}
             */
            processResults: function(selector, results) {
                var templates = [];
                $.each(results, function(index, template) {
                    templates.push({
                        value: template.id,
                        label: template._label
                    });
                });
                return templates;
            },

            /**
             * Transport method for autocomplete for templates.
             *
             * @param {type} selector
             * @param {type} query
             * @param {type} success
             * @param {type} failure
             * @returns {undefined}
             */
            transport: function(selector, query, success, failure) {
                var promise;
                var contextid = $(selector).data('contextid');
                if (contextid === '') {
                    return [];
                }

                promise = ajax.call([{
                    methodname: 'report_lpmonitoring_search_templates',
                    args: {
                        query: query,
                        contextid: parseInt(contextid)
                    }
                }]);

                promise[0].then(function(results) {
                    var promises = [],
                        i = 0;

                    // Render the label.
                    $.each(results, function(index, template) {
                        promises.push(templates.render('report_lpmonitoring/form-template-selector-suggestion', template));
                    });

                    // Apply the label to the results.
                    return $.when.apply($.when, promises).then(function() {
                        var args = arguments;
                        $.each(results, function(index, template) {
                            template._label = args[i];
                            i++;
                        });
                        success(results);
                    });

                }, failure);
            }
        };

    });
