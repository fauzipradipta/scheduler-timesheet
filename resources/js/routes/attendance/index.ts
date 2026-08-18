import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import entry from './entry'
/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:119
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
 * @see app/Http/Controllers/AttendanceController.php:119
 * @route '/attendance'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:119
 * @route '/attendance'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:119
 * @route '/attendance'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:147
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
 * @see app/Http/Controllers/AttendanceController.php:147
 * @route '/attendance'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:147
 * @route '/attendance'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:226
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
 * @see app/Http/Controllers/AttendanceController.php:226
 * @route '/attendance/download'
 */
download.url = (options?: RouteQueryOptions) => {
    return download.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:226
 * @route '/attendance/download'
 */
download.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:226
 * @route '/attendance/download'
 */
download.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:138
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
 * @see app/Http/Controllers/AttendanceController.php:138
 * @route '/attendance/holidays'
 */
holidays.url = (options?: RouteQueryOptions) => {
    return holidays.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:138
 * @route '/attendance/holidays'
 */
holidays.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: holidays.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::holidays
 * @see app/Http/Controllers/AttendanceController.php:138
 * @route '/attendance/holidays'
 */
holidays.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: holidays.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceController::upload
 * @see app/Http/Controllers/AttendanceController.php:246
 * @route '/attendance/upload'
 */
export const upload = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})

upload.definition = {
    methods: ["post"],
    url: '/attendance/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceController::upload
 * @see app/Http/Controllers/AttendanceController.php:246
 * @route '/attendance/upload'
 */
upload.url = (options?: RouteQueryOptions) => {
    return upload.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::upload
 * @see app/Http/Controllers/AttendanceController.php:246
 * @route '/attendance/upload'
 */
upload.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})
const attendance = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
download: Object.assign(download, download),
holidays: Object.assign(holidays, holidays),
upload: Object.assign(upload, upload),
entry: Object.assign(entry, entry),
}

export default attendance