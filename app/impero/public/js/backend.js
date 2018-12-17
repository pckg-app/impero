import PckgPingComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckgPing.vue";
import VueComponentGmapsComponent from "../../../../vendor/pckg/helpers-js/vue/gmaps.vue";
import PckgDynamicPaginatorComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-dynamic-paginator.vue";
import PckgTabelizeFieldDatetimeComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-datetime.vue";
import PckgTabelizeFieldOrderComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-order.vue";
import PckgTabelizeFieldBooleanComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-boolean.vue";
import PckgTabelizeFieldEditorComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-tabelize-field-editor.vue";
import PckgHtmlbuilderSelectComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-htmlbuilder-select.vue";
import PckgMaestroActionsComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/View/_pckg_maestro_actions.vue";
import PckgMaestroFieldComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/View/_pckg_maestro_field.vue";
import PckgBootstrapAlertComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-bootstrap-alert.vue";
import PckgBootstrapModalComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-bootstrap-modal.vue";
import PckgDispatcherNotificationsComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/dispatcherNotifications.vue";

import ImperoServersOne from "../../src/Impero/Servers/View/servers/one.vue";

import "../../../../vendor/pckg/helpers-js/webpack/orm.js";
import ImperoStore from "../../src/Pckg/Generic/public/store.impero";
import { Person, People, Tasks, Services, Service } from "../../src/Pckg/Generic/public/impero.js";

Pckg.vue.stores.impero = ImperoStore;

(async function(){


    /*let tasks = await (new Tasks()).get();
    console.log('all tasks', tasks);

    let task = tasks[0];
    console.log('first task', task);

    console.log(task.someAction());
    console.log(task.someAction());
    console.log(task.someComputedProperty);
    console.log(task.someComputedProperty);
    task.id = 'ažblj';
    console.log(task.id);*/

    /*let BojanRecord;
    BojanRecord = new Person({id: 4, name: 'Bojan'});
    BojanRecord.id = 2;
    console.log(BojanRecord.testAttr);
    //await BojanRecord.save().catch(function(){});
    BojanRecord.id = 3;
    //BojanRecord.save();
    console.log(BojanRecord.testAttrMethod());

    let admins = (new People()).getAdmins();

    admins.catch(function(e){
        console.log('error promising admins', e);
        return null;
    }).then(function(result){
        console.log("promised admins are");
        //console.log(result[0]);
        //console.log(result[0].testAttr);
        console.log(result[0].testAttrMethod());
        //console.log(result[0].id);
        //console.log(result[0].name);
    });

    return;

    console.log('by', admins);

    let admins2 = await (new People()).getAdmins();

    console.log('by2', admins2[0].name, Person.testAttrMethod(), admins2[0].testAttr);

    let p = new Person({id: 123});
    console.log("single", p, p.testAttr);

    Person.get(1);
    Person.get({id: 1});
    Person.get({id: 1, name: 'Bojan'});

    BojanRecord = new Person({id: 4, name: 'Bojan'});
    BojanRecord.delete();*/

})();

Vue.component('pckg-ping', PckgPingComponent);
Vue.component('vue-component-gmaps', VueComponentGmapsComponent);
Vue.component('pckg-dynamic-paginator', PckgDynamicPaginatorComponent);
Vue.component('pckg-tabelize-field-datetime', PckgTabelizeFieldDatetimeComponent);
Vue.component('pckg-tabelize-field-order', PckgTabelizeFieldOrderComponent);
Vue.component('pckg-tabelize-field-boolean', PckgTabelizeFieldBooleanComponent);
Vue.component('pckg-tabelize-field-editor', PckgTabelizeFieldEditorComponent);
Vue.component('pckg-htmlbuilder-select', PckgHtmlbuilderSelectComponent);
window.pckgMaestroActionsComponent = Vue.component('pckg-maestro-actions', PckgMaestroActionsComponent);
Vue.component('pckg-maestro-field', PckgMaestroFieldComponent);
Vue.component('pckg-bootstrap-alert', PckgBootstrapAlertComponent);
Vue.component('pckg-bootstrap-modal', PckgBootstrapModalComponent);
Vue.component('pckg-dispatcher-notifications', PckgDispatcherNotificationsComponent);

Vue.component('impero-servers-one', ImperoServersOne);