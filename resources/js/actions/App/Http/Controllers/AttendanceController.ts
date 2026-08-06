import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:80
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
 * @see app/Http/Controllers/AttendanceController.php:80
 * @route '/attendance'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:80
 * @route '/attendance'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::index
 * @see app/Http/Controllers/AttendanceController.php:80
 * @route '/attendance'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:90
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
 * @see app/Http/Controllers/AttendanceController.php:90
 * @route '/attendance'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::store
 * @see app/Http/Controllers/AttendanceController.php:90
 * @route '/attendance'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:178
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
 * @see app/Http/Controllers/AttendanceController.php:178
 * @route '/attendance/download'
 */
download.url = (options?: RouteQueryOptions) => {
    return download.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:178
 * @route '/attendance/download'
 */
download.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceController::download
 * @see app/Http/Controllers/AttendanceController.php:178
 * @route '/attendance/download'
 */
download.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceController::upload
 * @see app/Http/Controllers/AttendanceController.php:192
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
 * @see app/Http/Controllers/AttendanceController.php:192
 * @route '/attendance/upload'
 */
upload.url = (options?: RouteQueryOptions) => {
    return upload.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::upload
 * @see app/Http/Controllers/AttendanceController.php:192
 * @route '/attendance/upload'
 */
upload.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceController::storeEntry
 * @see app/Http/Controllers/AttendanceController.php:113
 * @route '/attendance/entry'
 */
export const storeEntry = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeEntry.url(options),
    method: 'post',
})

storeEntry.definition = {
    methods: ["post"],
    url: '/attendance/entry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceController::storeEntry
 * @see app/Http/Controllers/AttendanceController.php:113
 * @route '/attendance/entry'
 */
storeEntry.url = (options?: RouteQueryOptions) => {
    return storeEntry.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::storeEntry
 * @see app/Http/Controllers/AttendanceController.php:113
 * @route '/attendance/entry'
 */
storeEntry.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeEntry.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceController::destroyEntry
 * @see app/Http/Controllers/AttendanceController.php:163
 * @route '/attendance/entry/{entry}'
 */
export const destroyEntry = (args: { entry: string | number } | [entry: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyEntry.url(args, options),
    method: 'delete',
})

destroyEntry.definition = {
    methods: ["delete"],
    url: '/attendance/entry/{entry}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AttendanceController::destroyEntry
 * @see app/Http/Controllers/AttendanceController.php:163
 * @route '/attendance/entry/{entry}'
 */
destroyEntry.url = (args: { entry: string | number } | [entry: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroyEntry.definition.url
            .replace('{entry}', parsedArgs.entry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceController::destroyEntry
 * @see app/Http/Controllers/AttendanceController.php:163
 * @route '/attendance/entry/{entry}'
 */
destroyEntry.delete = (args: { entry: string | number } | [entry: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyEntry.url(args, options),
    method: 'delete',
})
const AttendanceController = { index, store, download, upload, storeEntry, destroyEntry }

export default AttendanceController