export default (reactive: any) => ({
    install(app: any) {
        window.Airwire.reactive = reactive;
        app.config.globalProperties.$airwire = window.Airwire
    }
})
