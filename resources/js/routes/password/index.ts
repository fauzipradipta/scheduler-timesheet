import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
export const request = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: request.url(options),
    method: 'get',
})

request.definition = {
    methods: ["get","head"],
    url: '/reset-password',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
request.url = (options?: RouteQueryOptions) => {
    return request.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
request.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: request.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
request.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: request.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
    const requestForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: request.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
        requestForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: request.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\ResetPasswordController::request
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:16
 * @route '/reset-password'
 */
        requestForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: request.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    request.form = requestForm
/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::store
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:26
 * @route '/reset-password'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/reset-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::store
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:26
 * @route '/reset-password'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\ResetPasswordController::store
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:26
 * @route '/reset-password'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\ResetPasswordController::store
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:26
 * @route '/reset-password'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\ResetPasswordController::store
 * @see app/Http/Controllers/Auth/ResetPasswordController.php:26
 * @route '/reset-password'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const password = {
    request: Object.assign(request, request),
store: Object.assign(store, store),
}

export default password