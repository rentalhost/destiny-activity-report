import _ from "lodash";
import Vue from "vue";
import $ from "jquery";

require('./FormController');

_.each([
    'Application',
    'Boxes',
    'Box',
    'Button',
    'ClanAccount',
    'ClanList',
    'Clock',
    'Input',
    'Messages',
    'Messages.Message',
    'Textarea'
], function (componentName) {
    Vue.component('my-' + _.kebabCase(componentName).toLowerCase(), require('../vue/' + componentName));
});

_.each($('my-application'), function (node) {
    new Vue({ el: node });
});

require('../application/Process');
