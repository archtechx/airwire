declare module 'airwire' {
    export interface AirwireException {
        message: string,
        exception: string,
        file: string,
        line: number,
        trace: Array<{
            class: string,
            file: string,
            function: string,
            line: number,
            type: string,
        }>,
    }

    export interface AirwirePromise<T> extends Promise<T> {
        then<TResult1 = T, TResult2 = never>(onfulfilled?: ((value: T) => TResult1 | PromiseLike<TResult1>) | undefined | null, onrejected?: ((reason: AirwireException) => TResult2 | PromiseLike<TResult2>) | undefined | null): AirwirePromise<TResult1 | TResult2>;
        catch<TResult = never>(onrejected?: ((reason: AirwireException) => TResult | PromiseLike<TResult>) | undefined | null): AirwirePromise<T | TResult>;
    }

    export type NonFunctionPropertyNames<T> = { [K in keyof T]: T[K] extends Function ? never : K }[keyof T];
    export type NonFunctionProperties<T> = Pick<T, NonFunctionPropertyNames<T>>;
    export type FunctionProperties<T> = Omit<T, NonFunctionPropertyNames<T>>;
    export type StringKeys<T> = Pick<T, Extract<keyof T, string>>;
    export type WiredMethods<T> = StringKeys<Partial<FunctionProperties<Omit<T, 'mount' | 'watch'>>>>;
    export type WiredProperties<T> = StringKeys<Partial<NonFunctionProperties<Omit<T, 'errors' | '\$component'>>>>;
    export type Magic<T> = T & { [key: string]: any };

    export type ComponentResponse<Component> = {
        data: WiredProperties<Component>;
        metadata: {
            calls?: {
                [key in keyof WiredMethods<Component>]: any;
            };

            exceptions?: {
                [key in keyof WiredMethods<Component>]: AirwireException;
            };

            errors?: {
                [key in keyof WiredProperties<Component>]: string[];
            };

            readonly: Array<keyof WiredProperties<Component>>;

            [key: string]: any;
        }
    }

    type Watchers<T> = {
        responses: Array<(response: ComponentResponse<T>) => void>,
        errors: Array<(error: AirwireException) => void>,
    };

    export type TypeNames = keyof TypeMap

    export type TypeName<T> = T extends TypeNames
        ? TypeMap[T]
        : never
}
