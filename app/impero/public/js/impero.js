import PckgAuthFull from "../../../../vendor/pckg/auth/src/Pckg/Auth/View/basic.vue";
import SshServiceInstall from "../../src/Impero/Services/Service/Ssh/View/vue/install.vue";
import SshServiceConfigure from "../../src/Impero/Services/Service/Ssh/View/vue/configure.vue";
import SoftwarePropertiesCommonServiceInstall
    from "../../src/Impero/Services/Service/SoftwareProperties/View/vue/install.vue";
import SoftwarePropertiesCommonServiceConfigure
    from "../../src/Impero/Services/Service/SoftwareProperties/View/vue/configure.vue";

import ImperoServersOne from "../../src/Impero/Servers/View/servers/one.vue";
import ImperoServersOneApplications from "../../src/Impero/Servers/View/servers/one_applications.vue";
import ServiceAutoinstall from "../../src/Impero/Servers/View/servers/service_autoinstall.vue";
import ServiceAutoinstallRequirements from "../../src/Impero/Servers/View/servers/service_autoinstall_requirements.vue";

export default {
    install(Vue) {
        Vue.component('pckg-auth-full', PckgAuthFull);

        Vue.component('impero-service-install-ssh', SshServiceInstall);
        Vue.component('impero-service-configure-ssh', SshServiceConfigure);

        Vue.component('impero-service-install-software-properties-common', SoftwarePropertiesCommonServiceInstall);
        Vue.component('impero-service-configure-software-properties-common', SoftwarePropertiesCommonServiceConfigure);

        Vue.component('impero-servers-one', ImperoServersOne);
        Vue.component('impero-servers-one-applications', ImperoServersOneApplications);
        Vue.component('service-autoinstall', ServiceAutoinstall);
        Vue.component('service-autoinstall-requirements', ServiceAutoinstallRequirements);
    }
}
