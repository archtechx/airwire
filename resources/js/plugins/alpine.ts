let state = window as any;

export default () => {
    const deferrer = state.deferLoadingAlpine || function (callback: CallableFunction) { callback() }

    state.deferLoadingAlpine = function (callback: CallableFunction) {
        state.Alpine.addMagicProperty('$airwire', (el: any) => {
            return function (...args: any) {
                if (args) {
                    return window.Airwire.component(args[0], args[1], el.__x.$data.$reactive)
                }

                return window.Airwire;
            }
        })

        deferrer(callback)
    }
}
