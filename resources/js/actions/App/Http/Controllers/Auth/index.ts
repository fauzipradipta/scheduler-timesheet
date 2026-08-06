import RegisteredUserController from './RegisteredUserController'
import AuthenticatedSessionController from './AuthenticatedSessionController'
const Auth = {
    RegisteredUserController: Object.assign(RegisteredUserController, RegisteredUserController),
AuthenticatedSessionController: Object.assign(AuthenticatedSessionController, AuthenticatedSessionController),
}

export default Auth