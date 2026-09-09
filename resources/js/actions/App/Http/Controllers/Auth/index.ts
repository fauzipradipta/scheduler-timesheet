import RegisteredUserController from './RegisteredUserController'
import AuthenticatedSessionController from './AuthenticatedSessionController'
import ResetPasswordController from './ResetPasswordController'
const Auth = {
    RegisteredUserController: Object.assign(RegisteredUserController, RegisteredUserController),
AuthenticatedSessionController: Object.assign(AuthenticatedSessionController, AuthenticatedSessionController),
ResetPasswordController: Object.assign(ResetPasswordController, ResetPasswordController),
}

export default Auth