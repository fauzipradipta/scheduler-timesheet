import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\TimesheetTemplateController::store
 * @see app/Http/Controllers/TimesheetTemplateController.php:29
 * @route '/attendance/template'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/attendance/template',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TimesheetTemplateController::store
 * @see app/Http/Controllers/TimesheetTemplateController.php:29
 * @route '/attendance/template'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TimesheetTemplateController::store
 * @see app/Http/Controllers/TimesheetTemplateController.php:29
 * @route '/attendance/template'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\TimesheetTemplateController::store
 * @see app/Http/Controllers/TimesheetTemplateController.php:29
 * @route '/attendance/template'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\TimesheetTemplateController::store
 * @see app/Http/Controllers/TimesheetTemplateController.php:29
 * @route '/attendance/template'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\TimesheetTemplateController::destroy
 * @see app/Http/Controllers/TimesheetTemplateController.php:61
 * @route '/attendance/template'
 */
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/attendance/template',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\TimesheetTemplateController::destroy
 * @see app/Http/Controllers/TimesheetTemplateController.php:61
 * @route '/attendance/template'
 */
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TimesheetTemplateController::destroy
 * @see app/Http/Controllers/TimesheetTemplateController.php:61
 * @route '/attendance/template'
 */
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\TimesheetTemplateController::destroy
 * @see app/Http/Controllers/TimesheetTemplateController.php:61
 * @route '/attendance/template'
 */
    const destroyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\TimesheetTemplateController::destroy
 * @see app/Http/Controllers/TimesheetTemplateController.php:61
 * @route '/attendance/template'
 */
        destroyForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const TimesheetTemplateController = { store, destroy }

export default TimesheetTemplateController