<template>
    <div>
        <h4>mailo.foobar.si</h4>
        <ul>
            <li v-for="service in application.services">
                {{ service.servers.length }}x {{ service.name }}:
                <span v-for="server in service.servers" class="label label-default" style="margin-right: .5rem;">[{{ server.name }}]</span>
                <span class="label label-default">[+]</span>
            </li>
        </ul>

        <p>Scaling <b>{{ application.name }}</b>'s service <b>{{ service.name }}</b> to <b>{{ server.name }}</b></p>

        <pckg-loader v-if="!requirementsLoaded" visible="!requirementsLoaded"></pckg-loader>

        <b>Requirements: <span v-if="requirements.length == 0" class="clr-success">ok</span><span
                v-else="requirements.length == 0" class="clr-error">fix all issues</span></b>

        <service-autoinstall-requirements :service="service" :server="server"
                                          :application="application"
                                          :requirements="requirements"></service-autoinstall-requirements>

        <hr/>

        <button type="button" class="btn btn-default">Deploy service {{ service.name }} to server {{ server.name
            }}
        </button>
    </div>
</template>

<script>
    export default {
        props: {
            final: true,
            server: {
                default: function () {
                    return {
                        id: 1,
                        name: 'two.gonparty.eu',
                    };
                }
            },
            service: {
                default: function () {
                    return {
                        id: 1,
                        name: 'sendmail:transactional',
                    };
                }
            },
            application: {
                default: function () {
                    return {
                        id: 1,
                        name: 'mailo.foobar.si',
                        services: [
                            {
                                name: 'Web',
                                servers: [
                                    {
                                        name: 'Zero'
                                    },
                                    {
                                        name: 'One'
                                    }
                                ]
                            },
                            {
                                name: 'Cron',
                                servers: [
                                    {
                                        name: 'Zero:transactional'
                                    },
                                    {
                                        name: 'One:newsletter'
                                    }
                                ]
                            },
                            {
                                name: 'Storage',
                                servers: [
                                    {
                                        name: 'FRA1-01'
                                    }
                                ]
                            },
                            {
                                name: 'Database',
                                servers: [
                                    {
                                        name: 'Zero:master',
                                    },
                                    {
                                        name: 'One:slave',
                                    }
                                ]
                            },
                            {
                                name: 'RabbitMQ:newsletter',
                                servers: []
                            },
                            {
                                name: 'RabbitMQ:transactional',
                                servers: []
                            },
                            {
                                name: 'RabbitMQ:dedicated',
                                servers: []
                            }
                        ]
                    };
                }
            },
        },
        data: function () {
            return {
                requirementsLoaded: true,
                requirements: [
                    {
                        name: 'RabbitMQ (local)',
                        type: 'resource',
                        requirements: []
                    },
                    {
                        name: 'RabbitMQ (remote)',
                        type: 'resource',
                        requirements: [
                            {
                                name: 'Network connection',
                                type: 'network',
                                settings: {
                                    from: 'two.gonparty.eu',
                                    to: 'zero.gonparty.eu',
                                    port: 5123,
                                },
                                requirements: [
                                    {
                                        name: 'VPN service',
                                        type: 'service',
                                        requirements: []
                                    },
                                    {
                                        name: 'Firewall',
                                        type: 'service',
                                        requirements: []
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        name: 'Web',
                        type: 'service',
                        requirements: [

                            {
                                name: 'Database',
                                type: 'resource',
                                requirements: [
                                    {
                                        name: 'mailo_mailo',
                                        type: 'resource:database',
                                        requirements: []
                                    },
                                    {
                                        name: 'Network connection',
                                        type: 'network',
                                        settings: {
                                            from: 'two.gonparty.eu',
                                            to: 'zero.gonparty.eu',
                                            port: 3306,
                                        },
                                        requirements: [
                                            {
                                                name: 'VPN service',
                                                type: 'service',
                                                requirements: []
                                            },
                                            {
                                                name: 'Firewall',
                                                type: 'service',
                                                requirements: []
                                            }
                                        ]
                                    },
                                    {
                                        name: 'Network connection',
                                        type: 'network',
                                        settings: {
                                            from: 'two.gonparty.eu',
                                            to: 'one.gonparty.eu',
                                            port: 3306,
                                        },
                                        requirements: [
                                            {
                                                name: 'VPN service',
                                                type: 'service',
                                                requirements: []
                                            },
                                            {
                                                name: 'Firewall',
                                                type: 'service',
                                                requirements: []
                                            }
                                        ]
                                    }
                                ]
                            },
                            {
                                name: 'Network connection',
                                type: 'network',
                                settings: {
                                    from: 'zero.gonparty.eu',
                                    to: 'two.gonparty.eu',
                                    port: 80,
                                },
                                requirements: [
                                    {
                                        name: 'VPN service',
                                        type: 'service',
                                        requirements: []
                                    },
                                    {
                                        name: 'Firewall',
                                        type: 'service',
                                        requirements: []
                                    }
                                ]
                            },
                            {
                                name: 'Network connection',
                                type: 'network',
                                settings: {
                                    from: 'zero.gonparty.eu',
                                    to: 'two.gonparty.eu',
                                    port: 443,
                                },
                                requirements: [
                                    {
                                        name: 'VPN service',
                                        type: 'service',
                                        requirements: []
                                    },
                                    {
                                        name: 'Firewall',
                                        type: 'service',
                                        requirements: []
                                    }
                                ]
                            }
                        ]
                    }
                ]
            };
        },
        computed: {
            requirementsMet: function () {
                return this.final;
            }
        }
    }
</script>