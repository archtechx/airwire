import { TypeMap, Watchers, WiredProperties, WiredMethods, AirwireException, AirwirePromise, ComponentResponse, Magic, TypeName, TypeNames } from 'airwire'

export class Component<AirwireComponent = TypeMap[keyof TypeMap]>
{
    public proxy: any;

    public loading: boolean = false;

    public errors: Record<string, string[]> = {};

    public _proxyTarget: Component<AirwireComponent>;

    public watchers: Watchers<AirwireComponent> = { responses: [], errors: [] };

    public pausedRequests: boolean = false;
    public pendingChanges: Partial<{ [key in keyof WiredProperties<AirwireComponent>]: any }> = {};
    public pendingCalls: Partial<{ [key in keyof WiredMethods<AirwireComponent>]: any }> = {};

    public readonly: Partial<{ [key in keyof WiredProperties<AirwireComponent>]: any }> = {};

    public reactive: CallableFunction;

    constructor(
        public alias: keyof TypeMap,
        public state: any,
        reactive: CallableFunction|null = null,
    ) {
        this.reactive = reactive ?? window.Airwire.reactive;

        this.readonly = state.readonly ?? {};
        delete this.state.readonly;

        let component: Component<AirwireComponent> = this._proxyTarget = this.reactive(this);

        window.Airwire.components[alias] ??= [];
        window.Airwire.components[alias]?.push(component as any);
        // We never use `this` in this class, because we always want to refer to the singleton reactive proxy

        component.watch(response => {
            let mount = (response.metadata.calls as any)?.mount;

            if (mount) {
                component.replaceState(mount, response.metadata.readonly);
            }
        });

        this.proxy = new Proxy(component.state, {
            get(target, property: string | symbol) {
                if (property === 'deferred') {
                    return new Proxy(component.state, {
                        get(target, property) {
                            return component.proxy[property]
                        },

                        set(target, property, value) {
                            component.pendingChanges[property as keyof WiredProperties<AirwireComponent>] = value;

                            return true
                        }
                    });
                }

                if (property === 'readonly') {
                    return component.readonly;
                }

                if (property === '$component') {
                    return component;
                }

                // Methods are returned using wrapper methods bypass the Proxy
                let methods = ['watch', 'defer', 'refresh', 'remount'];
                if (typeof property === 'string' && methods.includes(property)) {
                    return function (...args: any[]) {
                        return component[property as keyof typeof component](...args);
                    };
                }

                // Whitelisted Component properties
                let properties = ['errors', 'loading'];
                if (typeof property === 'string' && properties.includes(property)) {
                    return component[property as keyof typeof component];
                }

                if (typeof property === 'string' && Object.keys(component.state).includes(property)) {
                    return component.state[property];
                }

                if (typeof property === 'string' && Object.keys(component.readonly).includes(property)) {
                    return component.readonly[property as keyof WiredProperties<AirwireComponent>];
                }

                if (typeof property === 'string' && !property.startsWith('__v') && property !== 'toJSON') {
                    return function (...args: any[]) {
                        return component.call.apply(component, [
                            property as keyof WiredMethods<AirwireComponent>,
                            ...args
                        ]);
                    }
                }
            },

            set(target, property: string, value) {
                component.update(property as keyof WiredProperties<AirwireComponent>, value);

                return true
            }
        })
    }

    public update(property: keyof WiredProperties<AirwireComponent>, value: any): Promise<ComponentResponse<AirwireComponent>> | null {
        this.state[property] = value;

        if (this.pausedRequests) {
            this.pendingChanges[property] = value;

            return null;
        }

        return this.request(property, {
            changes: { [property]: value }
        }, (json: ComponentResponse<AirwireComponent>) => {
            if (json?.metadata?.exceptions) {
                return Promise.reject(json.metadata.exceptions);
            }

            return json
        })
    }

    public call(method: keyof WiredMethods<AirwireComponent>, ...args: any[]): AirwirePromise<any> | null {
        if (this.pausedRequests) {
            this.pendingCalls[method] = args;

            return null;
        }

        return this.request(method, {
            calls: { [method]: args }
        }, (json: ComponentResponse<AirwireComponent>) => {
            if (json?.metadata?.exceptions) {
                return Promise.reject(json.metadata.exceptions[method] ?? json.metadata.exceptions);
            }

            return json;
        }).then((json: ComponentResponse<AirwireComponent>) => json?.metadata?.calls?.[method] ?? null);
    }

    public request(target: string, data: {
        calls?: { [key in string]: any[] },
        changes?: { [key in string]: any },
    }, callback: (json: ComponentResponse<AirwireComponent>) => any = (json: ComponentResponse<AirwireComponent>) => json): AirwirePromise<ComponentResponse<AirwireComponent>> {
        this.loading = true;

        let pendingChanges = this.pendingChanges;
        this.pendingChanges = {};

        let pendingCalls = this.pendingCalls;
        this.pendingCalls = {};

        let path = window.Airwire.route;

        return fetch(`${path}/${this.alias}/${target}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                state: this.state,
                calls: { ...pendingCalls, ...data?.calls ?? {} },
                changes: { ...pendingChanges, ...data?.changes ?? {} },
            })
        })
            .then(response => response.json())
            .then((json: ComponentResponse<AirwireComponent>) => {
                this.loading = false
                window.Airwire.watchers.responses.forEach((watcher: any) => watcher(json as any))
                this.watchers.responses.forEach(watcher => watcher(json))

                return callback(json)
            })
            .catch((reason: AirwireException) => {
                this.loading = false
                window.Airwire.watchers.errors.forEach((watcher: any) => watcher(reason))
                this.watchers.errors.forEach(watcher => watcher(reason))

                return reason
            })
            .then((json: ComponentResponse<AirwireComponent>) => {
                if (json?.metadata?.errors) {
                    this.errors = json.metadata.errors
                }

                this.replaceState(json.data, json?.metadata?.readonly)

                return json
            })
    }

    public replaceState(state: any, readonly: any[]) {
        Object.entries(state).forEach(([key, value]) => {
            if (readonly && readonly.includes && readonly.includes(key)) {
                this.readonly[key as keyof WiredProperties<AirwireComponent>] = value;

                // Clean up state if the property wasn't readonly from the beginning
                if (this.state[key] !== undefined) {
                    delete this.state[key];
                }
            } else {
                this.state[key] = value;
            }
        })
    }

    public watch(responses: (response: ComponentResponse<AirwireComponent>) => void, errors?: (error: AirwireException) => void): void {
        this.watchers.responses.push(responses);

        if (errors) {
            this.watchers.errors.push(errors);
        }
    }

    public defer<T>(callback: () => T): T | null {
        this.pausedRequests = true;

        let result = null;
        try {
            result = callback();
        } catch (e) {
            this.pausedRequests = false;

            throw e
        }

        this.pausedRequests = false;

        return result;
    }

    public refresh() {
        return this.request('refresh', {});
    }

    public remount(...args: any[]) {
        return this.request('mount', {
            calls: {
                mount: args,
            }
        });
    }
}

export class Airwire {
    public route: string = '/airwire';

    public watchers: Watchers<TypeMap[keyof TypeMap]> = { responses: [], errors: [] };

    public components: Partial<{ [T in keyof TypeMap]: Array<Component<TypeMap[T]>> }> = {};

    public constructor(
        public componentDefaults: any = {},
        public reactive: CallableFunction = (component: Component) => component,
    ) { }

    public watch(responses: (response: ComponentResponse<TypeMap[keyof TypeMap]>) => void, errors?: (error: AirwireException) => void): Airwire {
        this.watchers.responses.push(responses);

        if (errors) {
            this.watchers.errors.push(errors);
        }

        return this;
    }

    public remount(aliases: keyof TypeMap | Array<keyof TypeMap> | null = null): void {
        this.refresh(aliases, true)
    }

    public refresh(aliases: keyof TypeMap | Array<keyof TypeMap> | null = null, remount: boolean = false): void {
        if (typeof aliases === 'string') {
            aliases = [aliases];
        }

        if (! aliases) {
            aliases = Object.keys(this.components) as Array<keyof TypeMap>;
        }

        for (const alias of aliases) {
            this.components[alias]?.forEach((component: Component<any>) => {
                if (remount) {
                    component.remount();
                } else {
                    component.refresh();
                }
            });
        }

    }

    public component<K extends TypeNames>(alias: K, state: WiredProperties<TypeName<K>>, reactive: CallableFunction | null = null): Magic<TypeName<K>> {
        const component = new Component<TypeName<K>>(alias, {
            ...this.componentDefaults[alias] ?? {},
            ...state
        }, reactive);

        return component.proxy as TypeName<K>;
    }

    public plugin(name: string) {
        return require('./plugins/' + name).default;
    }
}

export default Airwire;
