import $ from "jquery";
import EventBus from "./EventBus";

const FormController = {
    setEnabled: function (fields, mode) {
        $(fields).attr('disabled', mode === false);
    }
};

EventBus.$on('FormController:setEnabled', FormController.setEnabled);

export default FormController;
