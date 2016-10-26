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
 * Learning plan report navigation.
 *
 * @package    report_learningplan
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2016 Université de Montréal
 */

define(['jquery',
    'core/templates',
    'core/ajax',
    'core/notification',
    'core/str',
    'report_lpmonitoring/Chart',
    'core/form-autocomplete',
    'tool_lp/dialogue',
    'report_lpmonitoring/user_competency_popup',
    'tool_lp/grade_user_competency_inline',
    'report_lpmonitoring/fieldsettoggler',
    'report_lpmonitoring/colorcontrast',
    'report_lpmonitoring/paginated_datatable'],
    function($, templates, ajax, notification, str, Chart, autocomplete, Dialogue, Popup, InlineGrader, fieldsettoggler,
            colorcontrast, DataTable) {

        /**
         * Learning plan report.
         * @param {Boolean} userview True if the report is for user view (student).
         */
        var LearningplanReport = function(userview) {
            this.userView = userview || false;

            // Init the form filter.
            this.initPage();

            // Init the color contrast object.
            this.colorContrast = colorcontrast.init();

            // Init User competency page popup.
            var learningplan = this;
            var popup = new Popup('[data-region=list-competencies-section]', '[data-user-competency=true]');
            // Override the after show refresh method of the user competency popup.
            popup._refresh = function() {
                var self = this;
                learningplan.reloadCompetencyDetail(self._competencyId, self._userId, self._planId);
            };

            $(this.templateSelector).on('change', this.templateChangeHandler.bind(this)).change();
            $(this.learningplanSelector).on('change', this.learningplanChangeHandler.bind(this)).change();
            $(this.studentSelector).on('change', this.studentChangeHandler.bind(this)).change();
            $(this.studentPlansSelector).on('change', this.studentPlansChangeHandler.bind(this)).change();

            $('.competencyreport').on('change',
                '.scalefiltercontainer input[name="optionscalefilter"]',
                this.changeScaleApplyHandler.bind(this)).change();
            $('.competencyreport').on('change',
                '.scalesortordercontainer input[name="optionscalesortorder"]',
                this.changeScaleSortorderHandler.bind(this)).change();
            $('.competencyreport').on('change','.scalefiltervalues' ,this.changeScaleHandler.bind(this)).change();
            $('.competencyreport input[name=optionfilter]').prop("disabled", false);
            $('.competencyreport input[name=optionscalesortorder]').prop("disabled", false);
        };

        /** @var {Number} The template ID. */
        LearningplanReport.prototype.templateId = null;
        /** @var {Boolean} If report is for user view */
        LearningplanReport.prototype.userView = false;
        /** @var {Number} The learning plan ID from template. */
        LearningplanReport.prototype.learningplanId = null;
        /** @var {Number} The learning plan ID from student. */
        LearningplanReport.prototype.studentLearningplanId = null;
        /** @var {Number} The user ID. */
        LearningplanReport.prototype.userId = null;
        /** @var {Boolean} If template option is selected. */
        LearningplanReport.prototype.templateSelected = null;
        /** @var {Boolean} If student option is selected. */
        LearningplanReport.prototype.studentSelected = null;
        /** @var {Array} Competencies informations. */
        LearningplanReport.prototype.competencies = {};
        /** @var {String} Scales values filter. */
        LearningplanReport.prototype.scalesvaluesSelected = null;
        /** @var {ColorContrast} ColorContrast object instance. */
        LearningplanReport.prototype.colorcontrast = null;
        /** @var {Boolean} Apply scale filters on grade in course. */
        LearningplanReport.prototype.scalefilterbycourse = null;
        /** @var {String} Apply scale sortorder. */
        LearningplanReport.prototype.scalesortorder = 'ASC';

        /** @var {String} The template select box selector. */
        LearningplanReport.prototype.templateSelector = "#templateSelectorReport";
        /** @var {String} The learing plan select box selector. */
        LearningplanReport.prototype.learningplanSelector = '#learningplanSelectorReport';
        /** @var {String} The student selector. */
        LearningplanReport.prototype.studentSelector = '#studentSelectorReport';
        /** @var {String} The student plans selector. */
        LearningplanReport.prototype.studentPlansSelector = '#studentPlansSelectorReport';

        /**
         * Triggered when a template is selected.
         *
         * @name   templateChangeHandler
         * @param  {Event} e
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.templateChangeHandler = function(e) {
            var self = this;
            self.templateId = $(e.target).val();
            $(self.learningplanSelector).data('templateid', self.templateId);
            $(self.learningplanSelector).data('scalefilter', '');
            $(self.learningplanSelector).data('scalesortorder', '');
            self.resetUserUsingLPTemplateSelection();
            self.learningplanId = null;
            if (self.templateId !== '') {
                $('.competencyreport .moreless-actions').removeClass('hidden');
                if ($('.competencyreport .show-toggler').hasClass('hidden')) {
                    $('.competencyreport .fitem_scales').show();
                }
                self.loadScalesFromTemplate(self.templateId);
            } else {
                $('.competencyreport .moreless-actions').addClass('hidden');
                $('.competencyreport .fitem_scales').hide();
                $('.competencyreport #scale').empty();
            }
            self.checkDataFormReady();
        };

        /**
         * Reset the user using learning plan template selection.
         *
         * @name   resetUserUsingLPTemplateSelection
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.resetUserUsingLPTemplateSelection = function() {
            var self = this,
            autocomplete = $('.competencyreport .templatefilter .form-autocomplete-selection'),
            selection = autocomplete.find('span[aria-selected="true"]');
            self.learningplanId = null;
            if (selection.length) {
                selection.remove();
                $(self.learningplanSelector + ' option').remove();
                str.get_string('nostudentselected', 'report_lpmonitoring').done(
                    function(nostudentselected) {
                        autocomplete.append($('<span>').text(nostudentselected));
                    }
                );
            }
        };

        /**
         * Load scales from template.
         *
         * @name   loadScalesFromTemplate
         * @param  {Number} templateid
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.loadScalesFromTemplate = function(templateid) {
            var promise = ajax.call([{
                methodname: 'report_lpmonitoring_get_scales_from_template',
                args: {
                    templateid: parseInt(templateid)
                }
            }]);
            promise[0].then(function(results) {
                var context = {};
                context.scales = results;
                templates.render('report_lpmonitoring/scale_filter', context).done(function(html, js) {
                    $('.competencyreport #scale').html(html);
                    templates.runTemplateJS(js);
                });
                if (results.length > 0) {
                    $('.competencyreport #scalefilterapply').show();
                    templates.render('report_lpmonitoring/scale_filter_apply', context).done(function(html, js) {
                        $('.competencyreport #scalefilter').html(html);
                        templates.runTemplateJS(js);
                    });
                    $('.competencyreport #scalesortorderlabel').show();
                    templates.render('report_lpmonitoring/scale_filter_sortorder', context).done(function(html, js) {
                        $('.competencyreport #scalesortorder').html(html);
                        templates.runTemplateJS(js);
                    });
                } else {
                    $('.competencyreport #scalefilterapply').hide();
                    $('.competencyreport #scalesortorderlabel').hide();
                    $('.competencyreport #scalefilter').html('');
                    $('.competencyreport #scalesortorder').html('');
                }
            }).fail(
                function(exp) {
                    notification.exception(exp);
                }
            );
        };

        /**
         * Build options for learning plan.
         *
         * @name   buildLearningplanOptions
         * @param  {Array} options
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.buildLearningplanOptions = function(options) {
            var self = this;
            // Reset options scales.
            $(self.scaleSelector + ' option').remove();
            $(self.scaleSelector).append($('<option>'));

            $.each(options, function(key, value) {
                $(self.scaleSelector).append($('<option>').text(value.name).val(value.id));
            });
        };

        /**
         * Triggered when a learning plan is selected.
         *
         * @name   learningplanChangeHandler
         * @param  {Event} e
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.learningplanChangeHandler = function(e) {
            var self = this;
            self.learningplanId = $(e.target).val();
            self.checkDataFormReady();
        };

        /**
         * Triggered when a student is selected.
         *
         * @name   studentChangeHandler
         * @param  {Event} e
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.studentChangeHandler = function(e) {
            var self = this;
            self.userId = $(e.target).val();
            if (self.userId !== null) {
                var promise = ajax.call([{
                    methodname: 'core_competency_list_user_plans',
                    args: {
                        userid: self.userId
                    }
                }]);

                promise[0].then(function(results) {
                    // Reset options learning plans.
                    $(self.studentPlansSelector + ' option').remove();
                    if (results.length > 0) {
                        $(self.studentPlansSelector).prop("disabled", false);
                        $.each(results, function(key, value) {
                            $(self.studentPlansSelector).append($('<option>').text(value.name).val(value.id));
                        });
                    } else {
                        $(self.studentPlansSelector).prop("disabled", true);
                        str.get_string('nolearningplanavailable', 'report_lpmonitoring').done(
                            function(nolearningplanavailable) {
                                $(self.studentPlansSelector).append($('<option>').text(nolearningplanavailable).val(''));
                            }
                        );
                    }
                    $(self.studentPlansSelector).trigger('change');
                }, notification.exception);
            }
            self.checkDataFormReady();
        };

        /**
         * Triggered when a student plans is selected.
         *
         * @name   studentPlansChangeHandler
         * @param  {Event} e
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.studentPlansChangeHandler = function(e) {
            var self = this;
            self.studentLearningplanId = $(e.target).val();
            self.checkDataFormReady();
        };

        /**
         * Check if we can submit the form.
         *
         * @name   checkDataFormReady
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.checkDataFormReady = function() {
            var self = this,
                conditionByTemplate = false,
                conditionStudent = false;

            if (self.userView === false) {
                conditionByTemplate = $('#template').is(':checked') && $(self.templateSelector).val() !== '';
                conditionStudent = $('#student').is(':checked') && $(self.studentSelector).val() !== null &&
                        $(self.studentPlansSelector).val() !== null &&
                        $(self.studentPlansSelector).val() !== '';
            } else {
                conditionStudent = $(self.studentPlansSelector).val() !== null &&
                        $(self.studentPlansSelector).val() !== '';
            }

            if (conditionByTemplate || conditionStudent) {
                $('#submitFilterReportButton').removeAttr('disabled');
            } else {
                $('#submitFilterReportButton').attr('disabled', 'disabled');
            }
        };

        /**
         * Load list of competencies of a specified plan.
         *
         * @name   loadListCompetencies
         * @param  {Object} Plan
         * @param  {Object} Loader element
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.loadListCompetencies = function(plan, elementloading) {
            var self = this;

            var promiselistCompetencies = ajax.call([{
                methodname: 'report_lpmonitoring_list_plan_competencies',
                args: {
                    id: plan.id
                }
            }]);
            promiselistCompetencies[0].then(function(results) {
                if (results.length > 0) {
                    var competencies = {competencies_list:results, plan:plan, hascompetencies: true};
                    return templates.render('report_lpmonitoring/list_competencies', competencies).done(function(html, js) {
                        $("#listPlanCompetencies").html(html);
                        templates.runTemplateJS(js);
                        self.loadCompetencyDetail(results, plan, elementloading);
                    });
                } else {
                    elementloading.removeClass('loading');
                    return templates.render('report_lpmonitoring/list_competencies', {}).done(function(html, js) {
                        $("#listPlanCompetencies").html(html);
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
         * @param {Object} Plan
         * @param {Object} loader element
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.loadCompetencyDetail = function(competencies, plan, element) {
            var requests = [];
            var self = this;

            $.each(competencies, function(index, record) {
                // Locally store user competency information.
                self.competencies[record.competency.id] = {usercompetency:record.usercompetency};
                requests.push({
                    methodname: 'report_lpmonitoring_get_competency_detail',
                    args: {
                        competencyid: record.competency.id,
                        userid: plan.user.id,
                        planid: plan.id
                    }
                });
            });

            var promises = ajax.call(requests);
            $.each(promises, function(index, promise) {
                promise.then(function(context) {
                    // Locally store competency information.
                    self.competencies[context.competencyid].competencydetail = context;
                    context.plan = plan;
                    templates.render('report_lpmonitoring/competency_detail', context).done(function(html, js) {
                        var compid = context.competencyid;
                        var userid = plan.user.id;
                        var planid = plan.id;
                        var scaleid = context.scaleid;
                        $('#comp-' + compid + ' .x_content').html(html);
                        if (context.cangrade) {
                            // Apply inline grader.
                            self.applyInlineGrader(compid, userid, planid, scaleid);
                        }

                        // Apply Donut Graph to the competency.
                        if (context.hasrating !== false) {
                            self.ApplyDonutGraph(compid, context);
                        }
                        // If all template are loaded then hide the loader.
                        if (index === requests.length - 1) {
                            element.removeClass('loading');
                            // Show collapse links.
                            $('.competencyreport .competency-detail a.collapse-link').css('visibility', '');
                        }
                        templates.runTemplateJS(js);
                        self.colorContrast.apply('#comp-' + compid + ' .x_content .tile-stats .label.cr-scalename');
                    });
                });
            });
        };

        /**
         * Apply inline grader for the rate button.
         *
         * @name  applyInlineGrader
         * @param {Number} Competency ID
         * @param {Number} User ID
         * @param {Number} Plan ID
         * @param {Number} Scale ID
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.applyInlineGrader = function(competencyid, userid, planid, scaleid) {
            var self = this;
            str.get_string('chooserating', 'tool_lp').done(
                function(chooserateoption) {
                    // Set the inline grader.
                    var grader = new InlineGrader('#rate_' + competencyid,
                        scaleid,
                        competencyid,
                        userid,
                        planid,
                        '',
                        chooserateoption
                    );
                    // Callback when finishing rating.
                    grader.on('competencyupdated', function() {
                        self.reloadCompetencyDetail(competencyid, userid, planid);
                    });
                }
            );
        };

        /**
         * Reload competency detail and proficiency.
         *
         * @name  reloadCompetencyDetail
         * @param {Number} Competency ID
         * @param {Number} User ID
         * @param {Number} Plan ID
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.reloadCompetencyDetail = function(competencyid, userid, planid) {
            var self = this;
            self.competencies[competencyid] = {};
            var scalefilterbycourse = self.scalefilterbycourse === false ? 0 : 1;
            var promise = ajax.call([{
                methodname: 'core_competency_read_plan',
                args: { id: planid }
            }, {
                methodname: 'report_lpmonitoring_get_competency_detail',
                args: {
                    competencyid: competencyid,
                    userid: userid,
                    planid: planid
                }
            },{
                methodname: 'report_lpmonitoring_read_plan',
                args: {
                    scalevalues: "",
                    templateid: null,
                    planid: planid,
                    scalefilterbycourse: scalefilterbycourse
                }
            }
            ]);

            promise[0].then(function(plan) {
                promise[1].then(function(results) {
                    // Locally store competency information.
                    self.competencies[results.competencyid].competencydetail = results;
                    results.plan = plan;
                    templates.render('report_lpmonitoring/competency_detail', results).done(function(html, js) {
                        $('#comp-' + results.competencyid + ' .x_content').html(html);
                        templates.runTemplateJS(js);
                        if (results.cangrade) {
                            // Apply inline grader.
                            self.applyInlineGrader(results.competencyid, userid, planid, results.scaleid);
                        }

                        // Apply Donut Graph to the competency.
                        if (results.hasrating !== false) {
                            self.ApplyDonutGraph(results.competencyid, results);
                        }
                        self.colorContrast.apply('#comp-' + results.competencyid + ' .x_content .tile-stats .label.cr-scalename');
                    });
                    templates.render('report_lpmonitoring/competency_proficiency', results).done(function(html, js) {
                        $('#comp-' + results.competencyid + ' span.level').html(html);
                        templates.runTemplateJS(js);
                    });
                    // Reload plan stats.
                    promise[2].then(function(results) {
                        templates.render('report_lpmonitoring/plan_stats_report',
                        {
                            plan:results.plan,
                            hascompetencies:true
                        }).done(function(html, js) {
                            $('#plan-stats-report').html(html);
                            templates.runTemplateJS(js);
                        });
                    });
                });
            });

        };

        /**
         * Apply Donut Grapth to the competency.
         *
         * @name   ApplyDonutGraph
         * @param  {Number} competencyid
         * @param  {Array} data
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.ApplyDonutGraph = function(competencyid, data) {
            var options = {
                legend: false,
                responsive: false,
                tooltips: {enabled: false}
            };
            var colors = [];
            var coursebyscales = [];
            $.each(data.scalecompetencyitems, function(index, record) {
                colors.push(record.color);
                coursebyscales.push(record.nbcourse);
            });
            new Chart($('#canvas-graph-' + competencyid), {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: coursebyscales,
                        backgroundColor: colors,
                        hoverBackgroundColor: []
                    }]
                },
                options: options
            });

        };

        /**
         * Submit filter form.
         *
         * @name   submitFormHandler
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.submitFormHandler = function() {
            var self = this;
            var templateSelected = $("#template").is(':checked');
            var templateid = null;
            var planid = null;
            if (templateSelected === true) {
                templateid = self.templateId;
                planid = self.learningplanId;
            } else {
                planid = self.studentLearningplanId;
            }
            self.scalesvaluesSelected = $(self.learningplanSelector).data('scalefilter');
            self.displayPlan(planid, templateid);
        };

        /**
         * Handler on scale change.
         *
         * @name   changeScaleHandler
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.changeScaleHandler = function() {
            var self = this;
            var scalefiltervalues = [];
            $('.competencyreport .scalefiltervalues').each(function () {
                if ($(this).is(":checked")) {
                    scalefiltervalues.push({scalevalue : $(this).data("scalevalue"), scaleid : $(this).data("scaleid")});
                }
            });

            if (scalefiltervalues.length > 0) {
                $('.competencyreport input[name=optionscalefilter]').prop("disabled", false);
                $('.competencyreport input[name=optionscalesortorder]').prop("disabled", false);

                if ($("#scalefiltercourse").is(":not(:checked)") && $("#scalefilterplan").is(":not(:checked)")) {
                    $('#scalefiltercourse').prop("checked", true);
                }
                if ($("#scalesortorderasc").is(":not(:checked)") && $("#scalesortorderdesc").is(":not(:checked)")) {
                    $('#scalesortorderasc').prop("checked", true);
                }
            } else {
                $('.competencyreport input[name=optionscalefilter]').prop("checked", false);
                $('.competencyreport input[name=optionscalefilter]').prop("disabled", true);

                $('.competencyreport input[name=optionscalesortorder]').prop("disabled", true);
                $('.competencyreport input[name=optionscalesortorder]').prop("checked", false);
            }
            self.changeScaleApplyHandler();
            self.changeScaleSortorderHandler();
            self.resetUserUsingLPTemplateSelection();
            var filterscaleinputs = JSON.stringify(scalefiltervalues);
            $(self.learningplanSelector).data('scalefilter', filterscaleinputs);
        };

        /**
         * Handler on scale filter application change.
         *
         * @name   changeScaleApplyHandler
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.changeScaleApplyHandler = function() {
            var self = this;

            self.scalefilterbycourse = '';
            if ($("#scalefilterplan").is(':checked')) {
                self.scalefilterbycourse = false;
            }
            if ($("#scalefiltercourse").is(':checked')) {
                self.scalefilterbycourse = true;
            }
            $(self.learningplanSelector).data('scalefilterapply', self.scalefilterbycourse);
        };

        /**
         * Handler on scale sort order change.
         *
         * @name   changeScaleSortorderHandler
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.changeScaleSortorderHandler = function() {
            var self = this;
            self.scalesortorder = 'ASC';
            if ($("#scalesortorderdesc").is(':checked')) {
                self.scalesortorder = 'DESC';
            }
            $(self.learningplanSelector).data('scalesortorder', self.scalesortorder);
        };

        /**
         * Display the list of evidences in competency.
         *
         * @name   displayEvidencelist
         * @param {Object} Evidence list
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.displayEvidencelist = function(evidences) {
            var self = this;
            if (evidences.listevidence.length > 0) {
                str.get_string('listofevidence', 'tool_lp').done(
                function(titledialogue) {
                    templates.render('report_lpmonitoring/list_evidences_in_competency', evidences)
                        .done(function(html) {
                            // Show the dialogue.
                            new Dialogue(
                                titledialogue,
                                html,
                                function(){
                                    DataTable.apply('#listevidencecompetency-' + evidences.competencyid);
                                },
                                self.destroyDialogue
                            );
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
        LearningplanReport.prototype.destroyDialogue = function(dialg) {
            dialg.close();
        };

        /**
         * Display the list of courses in competency.
         *
         * @name   displayCourselist
         * @param {Object[]} listcourses
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.displayCourselist = function(listcourses) {
            var self = this;
            if (listcourses.listtotalcourses.length > 0) {
                str.get_string('linkedcourses', 'tool_lp').done(
                function(titledialogue) {
                    templates.render('report_lpmonitoring/list_courses_in_competency', listcourses)
                        .done(function(html) {
                            // Show the dialogue.
                            new Dialogue(
                                titledialogue,
                                html,
                                function(){
                                    DataTable.apply('#listcoursecompetency-' + listcourses.competencyid);
                                },
                                self.destroyDialogue
                            );
                        }).fail(notification.exception);
                });
            }
        };

        /**
         * Display plan.
         *
         * @name   displayPlan
         * @param {Number} planid The learning plan ID
         * @param {Number} templateid The learning plan template ID
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.displayPlan = function(planid, templateid) {

            var elementloading = null,
                    self = this;
            if($('#plan-user-info').length) {
                elementloading = $('#plan-user-info');
            } else {
                elementloading = $("#reportFilter button");
            }
            elementloading.addClass('loading');
            // Hide collapse links as long as the competencies details are not displayed.
            $('.competencyreport .competency-detail a.collapse-link').css('visibility', 'hidden');

            var scalefilterbycourse = self.scalefilterbycourse === false ? 0 : 1;

            // Set scales values empty if not defined.
            self.scalesvaluesSelected = self.userView === false ? self.scalesvaluesSelected : "";

            var promise = ajax.call([{
                methodname: 'report_lpmonitoring_read_plan',
                args: {
                    planid: parseInt(planid),
                    templateid: parseInt(templateid),
                    scalevalues: self.scalesvaluesSelected,
                    scalefilterbycourse: scalefilterbycourse,
                    scalesortorder: self.scalesortorder
                }
            }]);
            promise[0].then(function(results) {
                results.templateid = parseInt(templateid);
                if (self.userView === false) {
                    return templates.render('report_lpmonitoring/user_info', results).done(function(html) {
                        $("#userInfoContainer").html(html);
                        self.loadListCompetencies(results.plan, elementloading);
                    });
                } else {
                    str.get_string('learningplancompetencies', 'report_lpmonitoring', results.plan.name).done(function(planname) {
                        $('#planInfoContainer h3').text(planname);
                        self.loadListCompetencies(results.plan, elementloading);
                    });
                }
            }).fail(
                    function(exp) {
                        elementloading.removeClass('loading');
                        if (exp.errorcode === 'emptytemplate') {
                            var exception = {exception:exp};
                            return templates.render('report_lpmonitoring/user_info', exception).done(function(html) {
                                $("#userInfoContainer").html(html);
                                $("#listPlanCompetencies").empty();
                                $("#plan-stats-report").empty();
                            });
                        } else {
                            notification.exception(exp);
                        }
                    }
                );
        };

        /**
         * Display the list of courses in competency.
         *
         * @name   displayScaleCourseList
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.displayScaleCourseList = function(listcourses) {
            var self = this;
            if (listcourses.scalecompetencyitem.listcourses.length > 0) {
                str.get_string('linkedcourses', 'tool_lp').done(
                function(titledialogue) {
                    templates.render('report_lpmonitoring/list_courses_in_scale_value', listcourses)
                        .done(function(html) {
                            // Show the dialogue.
                            new Dialogue(
                                titledialogue,
                                html,
                                function(){
                                    DataTable.apply('#listscalecoursecompetency-' + listcourses.competencyid);
                                },
                                self.destroyDialogue
                            );
                            self.colorContrast.apply('.moodle-dialogue-base .label.cr-scalename');
                        }).fail(notification.exception);
                });
            }
        };

        /**
         * Init the differents page blocks and inputs form.
         *
         * @name   initPage
         * @return {Void}
         * @function
         */
        LearningplanReport.prototype.initPage = function() {
            var self = this;
            str.get_strings([
                { key: 'selectstudent', component: 'report_lpmonitoring' },
                { key: 'nostudentselected', component: 'report_lpmonitoring' }]
            ).done(
                function (strings) {
                    // Autocomplete users in templates.
                    autocomplete.enhance(
                        self.learningplanSelector,
                        false,
                        'report_lpmonitoring/learningplan',
                        strings[0],
                        false,
                        true,
                        strings[1]);
                    // Autocomplete users.
                    autocomplete.enhance(
                        self.studentSelector,
                        false,
                        'tool_lp/form-user-selector',
                        strings[0],
                        false,
                        true,
                        strings[1]);
                    if (self.userView === false) {
                        if ($('.competencyreport #student').is(':checked')){
                            $('.competencyreport .templatefilter').addClass('disabled-option');
                        } else {
                            $('.competencyreport .studentfilter').addClass('disabled-option');
                        }
                    }
                    self.checkDataFormReady();
                }
            ).fail(notification.exception);
            $(".competencyreport").on('click', '.moreless-toggler', function(event) {
                event.preventDefault();
                $(this).toggleClass("hidden").siblings().removeClass('hidden');
                $(".fitem_scales").slideToggle("slow");
            });

            // Allow collapse of block panels.
            fieldsettoggler.init();

            // Collapse block panels.
            $(".competencyreport").on('click', '.collapse-link', function() {
                var e = $(this).closest(".x_panel"),
                t = $(this).find("i"),
                n = e.find(".x_content");
                t.toggleClass("fa-chevron-right fa-chevron-down");
                n.slideToggle();
                e.toggleClass("panel-collapsed");
            });

            // Handle click on scale number courses.
            $(".competencyreport").on('click', 'a.scaleinfo', function() {
                var competencyid = $(this).data("competencyid");
                var scalevalue = $(this).data("scalevalue");

                if (typeof self.competencies[competencyid] !== 'undefined') {
                    var listcourses = {};
                    var competencydetail = self.competencies[competencyid].competencydetail;
                    listcourses.scalecompetencyitem = competencydetail.scalecompetencyitems[scalevalue - 1];
                    listcourses.competencyid = competencyid;
                    self.displayScaleCourseList(listcourses);
                }
            });

            $('.competencyreport #student').on('change', function(){
                if ($(this).is(':checked')){
                    $('.competencyreport .studentfilter').toggleClass('disabled-option');
                    $('.competencyreport .templatefilter').toggleClass('disabled-option');
                }
                self.checkDataFormReady();
            });

            $('.competencyreport #template').on('change', function(){
                if ($(this).is(':checked')){
                    $('.competencyreport .studentfilter').toggleClass('disabled-option');
                    $('.competencyreport .templatefilter').toggleClass('disabled-option');
                }
                self.checkDataFormReady();
            });

            // Filter form submit.
            $(document).on('submit', '#reportFilter', function(){
                self.submitFormHandler();
                return false;
            });

            // User plan navigation.
            $(".competencyreport").on('click', 'a.navigatetoplan', function(event) {
                event.preventDefault();
                var planid = $(this).data('planid');
                var templateid = $(this).data('templateid');
                self.displayPlan(planid, templateid);
            });

            // Handle click on list evidence.
            $(".competencyreport").on('click', 'a.listevidence', function(event) {
                event.preventDefault();
                var competencyid = $(this).data('competencyid');
                if (typeof self.competencies[competencyid] !== 'undefined') {
                    var listevidence = {};
                    listevidence.listevidence = self.competencies[competencyid].competencydetail.listevidence;
                    listevidence.competencyid = competencyid;
                    self.displayEvidencelist(listevidence);
                }
            });

            // Handle click on total number courses.
            $(".competencyreport").on('click', 'a.totalnbcourses', function(event) {
                event.preventDefault();
                var competencyid = $(this).data('competencyid');
                if (typeof self.competencies[competencyid] !== 'undefined') {
                    var totallistcourses = {};
                    totallistcourses.listtotalcourses = self.competencies[competencyid].competencydetail.listtotalcourses;
                    totallistcourses.competencyid = competencyid;
                    self.displayCourselist(totallistcourses);
                }
            });

            // Collapse/Expand all.
            str.get_strings([
                { key: 'collapseall'},
                { key: 'expandall'}]
            ).done(
                function (strings) {
                    var collapseall = strings[0];
                    var expandall = strings[1];
                    $(".competencyreport").on('click', '.collapsible-actions a', function(event) {
                        event.preventDefault();
                        if ($(this).hasClass('collapse-all')) {
                            $(this).text(expandall);
                            $('#listPlanCompetencies div.x_panel:not(.panel-collapsed) a.collapse-link').trigger('click');
                        } else {
                            $(this).text(collapseall);
                            $('#listPlanCompetencies div.panel-collapsed a.collapse-link').trigger('click');
                        }
                        $(this).toggleClass("collapse-all expand-all");
                    });
                }
            ).fail(notification.exception);
        };

        return {
            /**
             * Main initialisation.
             *
             * @param {Boolean} True if the report is for user view (student).
             * @return {LearningplanReport} A new instance of ScaleConfig.
             * @method init
             */
            init: function(userview) {
                return new LearningplanReport(userview);
            },
            /**
             * Process result autocomplete.
             *
             * @param {type} selector
             * @param {type} results
             * @returns {Array}
             */
            processResults: function(selector, results) {
                var users = [];
                $.each(results, function(index, userplan) {
                    users.push({
                        value: userplan.planid,
                        label: userplan._label
                    });
                });
                return users;
            },

            /**
             * Transport method for autocomplete.
             *
             * @param {type} selector
             * @param {type} query
             * @param {type} success
             * @param {type} failure
             * @returns {undefined}
             */
            transport: function(selector, query, success, failure) {
                var promise;
                var scalefilterapply = $(selector).data('scalefilterapply');
                var scalesortorder = $(selector).data('scalesortorder');
                scalesortorder = scalesortorder ? scalesortorder : 'ASC';
                var scalefilterbycourse = scalefilterapply === false ? 0 : 1;
                var templateid = $(selector).data('templateid');
                if (templateid === '') {
                    return [];
                }

                promise = ajax.call([{
                    methodname: 'report_lpmonitoring_search_users_by_templateid',
                    args: {
                        query: query,
                        templateid: parseInt(templateid),
                        scalevalues: $(selector).data('scalefilter'),
                        scalefilterbycourse: scalefilterbycourse,
                        scalesortorder: scalesortorder
                    }
                }]);

                promise[0].then(function(results) {
                    var promises = [],
                        i = 0;

                    // Render the label.
                    $.each(results, function(index, user) {
                        var ctx = user;
                        promises.push(templates.render('report_lpmonitoring/form-user-selector-suggestion', ctx));
                    });

                    // Apply the label to the results.
                    return $.when.apply($.when, promises).then(function() {
                        var args = arguments;
                        $.each(results, function(index, user) {
                            user._label = args[i];
                            i++;
                        });
                        success(results);
                    });

                }, failure);
            }
        };

    });
