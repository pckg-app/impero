import {Entity, Record} from "../../../../../../vendor/pckg/helpers-js/webpack/orm";

export class Task extends Record {

    constructor(data) {
        super(data);
        this.$entity = Tasks;
    }

    /**
     * Can be called as object.someAction()
     * @returns {number}
     */
    someAction() {
        console.log('someAction');
        return 123 * Math.random();
    }

    /**
     * Can be retrieved as object.someComputedProperty
     * @returns {number}
     */
    get someComputedProperty() {
        console.log('someComputedProperty');
        return 223 * Math.random();
    }

}

export class Tasks extends Entity {

    constructor() {
        super();
        this.$path = 'tasks';
        this.$record = Task;
    }

}

export class Service extends Record {

    constructor(data) {
        super(data);
        this.$entity = Services;
    }

    isInstalledOnServer(server) {
        console.log('isInstalledOnServer', this, server);
        return (server.services || []).filter(function (service) {
            return service.id == this.id || service.service == this.service;
        }.bind(this)).length > 0;
    }

    getInstallOnServerUrl(server) {
        return utils.url('/api/services/[service]/install/[server]', {server: server.id, service: this.id});
    }

    saveServerSettings(server) {
        let settings = server.settings2[this.service];
        let entity = this.getEntity();
        entity.post([this.id || this.service, 'server', server.id, 'settings'], {settings: settings});
    }

    configure() {
        /**
         * We want to trigger a display of vue module.
         * Someone needs to listen to this.
         */
        $dispatcher.$emit('impero-service-' + this.service + '-configure:open');
    }

}

export class Services extends Entity {

    constructor() {
        super();
        this.$path = 'services';
        this.$record = Service;
    }

}

export class Dependency extends Record {

    constructor(data) {
        super(data);
        this.$entity = Dependencies;
    }

}

export class Dependencies extends Entity {

    constructor() {
        super();
        this.$path = 'dependencies';
        this.$record = Dependency;
    }

}

export class Website extends Record {

    constructor(data) {
        super(data);
        this.$entity = Websites;
    }

}

export class Websites extends Entity {

    constructor() {
        super();
        this.$path = 'websites';
        this.$record = Website;
    }

}

export class NetworkInterface extends Record {

    constructor(data) {
        super(data);
        this.$entity = NetworkInterfaces;
    }

}

export class NetworkInterfaces extends Entity {

    constructor() {
        super();
        this.$record = NetworkInterface;
    }

}

export class FirewallSetting extends Record {

    constructor(data) {
        super(data);
        this.$entity = FirewallSettings;
    }

}

export class FirewallSettings extends Entity {

    constructor() {
        super();
        this.$record = FirewallSetting;
    }

}

export class Server extends Record {

    constructor(data) {
        super(data);
        this.$entity = Servers;
    }

    $definition() {
        return {

            data: function () { // called when object.baz is called
                return {
                    baz: null,
                }
            },

            relations: ['services', 'dependencies', 'websites', 'networkInterfaces', 'firewallSettings'],

            methods: {

                bar: function () { // called when object.bar() is called

                }

            },

            getters: {

                foo: function () { // called when object.foo is called
                }

            },

            setters: {

                sth: function () { // called when object.sth = ... is called

                }

            }
        };
    }

    getServicesRelation() {
        return this.$autoFetch('services', Services);
    }

    getDependenciesRelation() {
        return this.$autoFetch('dependencies', Dependencies);
    }

    getWebsitesRelation() {
        return this.$autoFetch('websites', Websites);
    }

    getNetworkInterfacesRelation() {
        return this.$autoFetch(['network-interfaces', 'networkInterfaces'], NetworkInterfaces);
    }

    getFirewallSettingsRelation() {
        return this.$autoFetch(['firewall-settings', 'firewallSettings'], FirewallSettings);
    }

}


export class Servers extends Entity {

    constructor() {
        super();
        this.$path = 'servers';
        this.$record = Server;
    }

}