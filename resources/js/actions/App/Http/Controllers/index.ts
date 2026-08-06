import Auth from './Auth'
import AttendanceController from './AttendanceController'
const Controllers = {
    Auth: Object.assign(Auth, Auth),
AttendanceController: Object.assign(AttendanceController, AttendanceController),
}

export default Controllers