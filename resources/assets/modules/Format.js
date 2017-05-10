export default {
    // From: http://stackoverflow.com/a/25377176/755393
    thousands (number){
        return number.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    }
}
