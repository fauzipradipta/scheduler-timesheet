import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:113
 * @route '/attendance/entry'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/attendance/entry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:113
 * @route '/attendance/entry'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:113
 * @route '/attendance/entry'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceController::destroy
 * @see app/Http/Controllers/AttendanceController.php:163
 * @route '/attendance/entry/{entry}'
 */
export const destroy = (args: { entry: string | number } | [entry: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/attendance/entry/{entry}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AttendanceController::destroy
 * @see app/Http/Controllers/AttendanceController.php:163
 * @route '/attendance/entry/{entry}'
 */
destroy.url = (args: { entry: string | number } | [entry: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entry: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    entry: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entry: args.entry,
                }

    return destroy.definition.url
            .replace('{entry}', parsedArgs.entry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::destroy
 * @see app/Http/Controllers/AttendanceController.php:163
 * @route '/attendance/entry/{entry}'
 */
destroy.delete = (args: { entry: string | number } | [entry: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const entry = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default entry