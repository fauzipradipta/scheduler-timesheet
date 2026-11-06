import Auth from './Auth'
import ProfileController from './ProfileController'
import AttendanceController from './AttendanceController'
import TimesheetTemplateController from './TimesheetTemplateController'
const Controllers = {
    Auth: Object.assign(Auth, Auth),
ProfileController: Object.assign(ProfileController, ProfileController),
AttendanceController: Object.assign(AttendanceController, AttendanceController),
TimesheetTemplateController: Object.assign(TimesheetTemplateController, TimesheetTemplateController),
}

export default Controllers