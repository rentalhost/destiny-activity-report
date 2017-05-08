import qs from "querystring";

export default {
    get(param){
        return qs.parse(location.search.slice(1))[param];
    }
}
