import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import template from './template'
import entry from './entry'
/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/attendance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:70
 * @route '/attendance'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:102
 * @route '/attendance'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/attendance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:102
 * @route '/attendance'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:102
 * @route '/attendance'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:102
 * @route '/attendance'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:102
 * @route '/attendance'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
export const download = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/attendance/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
download.url = (options?: RouteQueryOptions) => {
    return download.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
download.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
download.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
    const downloadForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
        downloadForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:187
 * @route '/attendance/download'
 */
        downloadForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    download.form = downloadForm
/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
export const holidays = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: holidays.url(options),
    method: 'get',
})

holidays.definition = {
    methods: ["get","head"],
    url: '/attendance/holidays',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
holidays.url = (options?: RouteQueryOptions) => {
    return holidays.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
holidays.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: holidays.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
holidays.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: holidays.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
    const holidaysForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: holidays.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
        holidaysForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: holidays.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:93
 * @route '/attendance/holidays'
 */
        holidaysForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: holidays.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    holidays.form = holidaysForm
const attendance = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
download: Object.assign(download, download),
holidays: Object.assign(holidays, holidays),
template: Object.assign(template, template),
entry: Object.assign(entry, entry),
}

export default attendance