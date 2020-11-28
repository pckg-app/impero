import HtmlbuilderValidatorError from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/htmlbuilderValidatorError.vue";
import PckgTooltipComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckgTooltip.vue";
import PckgLoaderComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckgLoader.vue";
import PckgClipboardComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckgClipboard.vue";
import PckgSelectComponent from "../../../../vendor/pckg/helpers-js/vue/pckgSelect.vue";
import PckgDatetimeComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckgDatetime.vue";
import PckgHtmleditorComponent from "../../../../vendor/pckg/helpers-js/vue/pckgHtmleditor.vue";
import PckgDispatcherNotificationsComponent from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/dispatcherNotifications.vue";
import PckgBootstrapAlertComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-bootstrap-alert.vue";
import PckgBootstrapModalComponent from "../../../../vendor/pckg/generic/src/Pckg/Maestro/public/vue/pckg-bootstrap-modal.vue";
import PckgHtmlbuilderDropzoneComponent from "../../../../vendor/pckg/helpers-js/vue/pckg-htmlbuilder-dropzone.vue";
import VeeValidate from "../../../../node_modules/vee-validate/dist/vee-validate.min";
import PckgDatetimePicker from "../../../../vendor/pckg/generic/src/Pckg/Generic/View/pckg-datetime-picker.vue";

Vue.component('htmlbuilder-validator-error', HtmlbuilderValidatorError);
Vue.component('pckg-tooltip', PckgTooltipComponent);
Vue.component('pckg-loader', PckgLoaderComponent);
Vue.component('pckg-clipboard', PckgClipboardComponent);
Vue.component('pckg-select', PckgSelectComponent);
Vue.component('pckg-datetime', PckgDatetimeComponent);
Vue.component('pckg-htmleditor', PckgHtmleditorComponent);
Vue.component('pckg-dispatcher-notifications', PckgDispatcherNotificationsComponent);
Vue.component('pckg-bootstrap-alert', PckgBootstrapAlertComponent);
Vue.component('pckg-bootstrap-modal', PckgBootstrapModalComponent);
Vue.component('pckg-htmlbuilder-dropzone', PckgHtmlbuilderDropzoneComponent);
Vue.component('pckg-datetime-picker', PckgDatetimePicker);

Vue.use(VeeValidate);
