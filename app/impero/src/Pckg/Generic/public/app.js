var Impero = Impero || {};
Impero.Servers = Impero.Servers || {};
Impero.Servers.Record = Impero.Servers.Record || {};
Impero.Servers.Entity = Impero.Servers.Entity || {};
Impero.Servers.Entity.Servers = class extends Pckg.Database.Entity {
    static id(id, callback) {
        return http.getJSON(utils.url('@api.impero.servers.server', {server: id}), function (data) {
            callback(new Impero.Servers.Record.Server(data.server));
        });
    }

    getFields() {
        return {
            id: Number,
            system_id: Number,
            name: String,
            ip: String,
            ptr: String
        };
    }

    getRelations() {
        return {
            services: {
                type: Array,
                of: Impero.Servers.Record.Service
            },
            dependencies: {
                type: Array,
                of: Impero.Servers.Record.Dependency
            },
            jobs: {
                type: Array,
                of: Impero.Servers.Record.Job
            }
        };
    }

};
Impero.Servers.Entity.ServersServices = class extends Pckg.Database.Entity {

};
Impero.Servers.Entity.ServersDependencies = class extends Pckg.Database.Entity {

};
Impero.Servers.Entity.Services = class extends Pckg.Database.Entity {
    static id(id, callback) {
        return this.by('id', id, callback);
    }

    static by(key, value, callback) {
        return http.getJSON(utils.url('@api.impero.servers.server.services', {server: value}), function (data) {
            callback(Pckg.Collection.collect(data.services, Impero.Servers.Record.Service));
        });
    }

    getRelations() {
        return {
            pivot: {
                type: Object,
                of: Impero.Servers.Record.ServersService
            }
        };
    }

    getFields() {
        return {
            id: Number,
            name: String
        };
    }

    getCollections() {
        return {
            getAllServices: []
        };
    }

    static getAllServices() {
        return $store.getters.services;
    }
};
Impero.Servers.Entity.Dependencies = class extends Pckg.Database.Entity {
    static id(id, callback) {
        return this.by('id', id, callback);
    }

    static by(key, value, callback) {
        return http.getJSON(utils.url('@api.impero.servers.server.dependencies', {server: value}), function (data) {
            callback(Pckg.Collection.collect(data.dependencies, Impero.Servers.Record.Dependency));
        });
    }

    getRelations() {
        return {
            pivot: {
                type: Object,
                of: Impero.Servers.Record.ServersDependency
            }
        };
    }

    getFields() {
        return {
            id: Number,
            name: String
        };
    }
};
Impero.Servers.Entity.Jobs = class extends Pckg.Database.Entity {

    getRelations() {
        return {
            server: {
                type: Object,
                of: Impero.Servers.Record.Server
            }
        };
    }

    getFields() {
        return {};
    }
};

Impero.Servers.Record.Server = class extends Pckg.Database.Record {

    fetchServices() {
        Impero.Servers.Entity.Services.by('server_id', this.id, function (services) {
            this.services = services;
        }.bind(this));
    }

    refreshServicesStatuses() {
        $.each(this.services, function (i, service) {
            service.refreshStatus();
        });
    }

    getEntity() {
        return new Impero.Servers.Entity.Servers();
    }

    getUrl(type) {
        if (type == 'insert') {
            return utils.url('@impero.servers.addServer', {server: this.id});
        }
    }

};

Impero.Servers.Record.ServersService = class extends Pckg.Database.Record {

    getEntity() {
        return new Impero.Servers.Entity.ServersServices();
    }

};

Impero.Servers.Record.ServersDependency = class extends Pckg.Database.Record {

    getEntity() {
        return new Impero.Servers.Entity.ServersDependencies();
    }

};

Impero.Servers.Record.Service = class extends Pckg.Database.Record {

    isInstalled() {
        var status = this.getStatus();

        return ['Ok', 'Exited, ok'].indexOf(status) >= 0;
    }

    install() {
        return true;
    }

    configure() {
        return true;
    }

    getUrl(type) {
        if (type == 'refreshServersServiceStatus') {
            return utils.url('@impero.servers.refreshServersServiceStatus', {serversService: this.pivot.id});
        }
    }

    getInstallOnServerUrl(server) {
        return utils.url('@api.services.install', {server: server.id, service: this.id});
    }

    isInstalledOnServer(server) {
        return (server.services || []).filter(function (service) {
            return service.id == this.id;
        }.bind(this)).length > 0;
    }

    getEntity() {
        return new Impero.Servers.Entity.Services();
    }

    getStatus() {
        if (!(this.pivot && this.pivot.status && this.pivot.status.value)) {
            return '';
        }

        return this.pivot.status.value;
    }

    getVersion() {
        if (!(this.pivot && this.pivot.version)) {
            return '';
        }

        return this.pivot.version;
    }

};

Impero.Servers.Record.Dependency = class extends Pckg.Database.Record {

    refreshStatus() {
        http.getJSON(this.getUrl('refreshServersDependencyStatus'), function (data) {
            this.pivot = new Impero.Servers.Record.ServersDependency(data.serversDependency);
        }.bind(this));
    }

    getUrl(type) {
        if (type == 'refreshServersDependencyStatus') {
            return utils.url('@impero.servers.refreshServersDependencyStatus', {serversDependency: this.pivot.id});
        }
    }

    getEntity() {
        return new Impero.Servers.Entity.Dependencies();
    }

};

Impero.Servers.Record.Job = class extends Pckg.Database.Record {

    getEntity() {
        return new Impero.Servers.Entity.Jobs();
    }

};