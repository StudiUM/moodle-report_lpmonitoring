# Introduction

The main goal of this plugin is to facilitate the work of learning plan managers. 
It provides an overview of user learning plan, without leaving the page to get information related to this learning plan(such as rating in courses,user evidence,). It also offers statistics by learning plans and competencies.
For learning plan templates with a very large number of learning plans (generated from cohorts), this plugin is the best solution, with its advanced filter, it allows you to filter the learning plans by several criterias.

# Plugin configuration
## Role and permissions
This plugin can be used in system or category context.
The user using this plugin in the category must have these permissions:

* moodle/competency:competencyview
* moodle/competency:coursecompetencyview
* moodle/competency:usercompetencyview
* moodle/competency:usercompetencymanage
* moodle/competency:competencygrade
* moodle/competency:planview
* moodle/competency:planmanage
* moodle/competency:templateview

Note that planview is always set in user context level. Thus, we must assign a user to a cohort using `Site administration/Users/Permissions/Assign user roles to cohort` so he will have the "moodle/competency:planview" of every member of that specific cohort (must wait for the next cron execution to take effect).

## Color configuration

To fully benefit from the reports, a color must be associated to each value of the scale.
To set the scale values colors, you must have "moodle/competency:competencymanage" permission.
Here are the steps :
* Go on "Home / ► Site administration / ► Competencies / ► Competencies scale colors"
* Choose a framework competency
* Choose a scale from framework competency
* Set the colors values using the ColorPicker
* Save the changes

![Colors configuration](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoring_colors.PNG)

That's it, now the colors are set and ready to be used in the reports pages.

# Monitoring of learning plans page

In a category context or system context go on `Reports / ► Monitoring of learning plans`

## Filter

Filtering helps us find a user's learning plan. Here are the steps:

### By learning plan template

From a learning plan template, we can filter learning plans.
* Select a template
* From "Student using this template", select a user
* click on "Apply"

You will see the learning plan of the student selected, and you can navigate between learning plan based on that template.
If there is no student selected, the first student from the template will be displayed.

![Filter by template](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoring_filterbytemplatesimple.PNG)

By clicking on "show more...", you can have more options to filter learning plans by scales values.

![Filter scales values](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringscalesvaluesfilter.PNG)

There are two options for using scales values filter

* Filtering learning plans by scale values from competencies rated at course level
* Filtering learning plans by scales values from competencies rated in the plan (Final rating)

When filtering by scales values, the number of rating in the student list will be displayed:

![Filter rating number](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringscalesvaluesfilternbrating.png)

### By user
We can choose a particular student by typing his name in the user picker field in order to retrieve his learning plans 

![Filter by student](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoring_filterbystudent.png)

## Learning plan detail

The details of the learning plan is divided into three blocks:

![Filter by student](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringlearningplanglobal.PNG)

### 1.User navigation
This block contain the following informations:

* The learning plan name: link to the learning plan page
* The user full name and a profile picture: link to the user profile page
* The navigation between users that belong to the selected template.

![User navigation](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringusernavigation.PNG)

### 2.Learning plan competencies informations
This block displays the following informations:

* The plan's status and the number of competencies that are rated proficient on the total number of competencies of the plan
* The number competencies that are rated not proficient
* The number of competencies that are not rated

![Learning plan competencies informations](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringplancompetenciesinfo.PNG)

### 3.List competencies details

This part has three blocks

![List competencies details](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringplancompetencydetail.PNG)

**Total number of rating**

It display the number of courses linked to the competency and wherein the user is enrolled, Clicking on the number will trigger a popup containing the list of course linked to the competency and if the course was rated or not.

![Popup total number courses](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringplancompetencynbtotalcourse.PNG)

Also, this block displays the number of evidences of prior learning. You can have more details on the list of evidences by clicking on this number

![evidence of prior learning](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringplancompetencypopupevidence.PNG)

**Rating**

This block displays the number of rated courses by scale value. Clicking on this number triggers a popup that displays the course name, comments left by the teacher and the final course grade.

![Rated courses by scale value](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringlearningcourselistbyscalevalue.PNG)

**Final rating**

This block gives us details about the final rating (rating in learning plan) :
* if the competency is proficient, not proficient or not rated
* The rated scale value (if rated)
* A button to rate the competency if the user has the permission

![Competency final rating](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringlearningcompetencyfinalrating.PNG)

# Statistics for learning plans

This page provides statistics for learning plans.
It groups statistics by competency for a given template. For each competency we display the total users in template and number of users by scale value.
To get this page, go on `Reports / ► Statistics for learning plans`

![Stats page](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringstatistics.PNG)

**Total users**

Clicking on the "Total users" number triggers a popup with the list of all the users related to this competency. It shows if each user has been rated.

![Stats nb users](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringstatstotalusers.PNG)

**Number of users by scale value**

It displays the number of users rated with a given scale value for the competency.


![Stats user by scalevalue](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringstatsusersbyscalevalue.PNG)

# Monitoring of learning plans for users (students)

This page gives the user the ability to keep track of his learning plans with all the details mentioned above. To get this page go to the user profile page and click on Monitoring of learning plans in the reports block.

![Profile user](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringprofilepage.PNG)

To display the report, the user must select a learning plan and click on "Apply"

![Student lpmonitoring](https://wiki.umontreal.ca/download/attachments/124980567/report_lpmonitoringstudentdetail.PNG)


***
_Developed by_ ![Université de Montréal](http://www.umontreal.ca/images/iu/logo-udem.gif)
