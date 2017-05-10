export default {
    // From: http://stackoverflow.com/a/25377176/755393
    thousands (number){
        return number.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    },
    // From: http://stackoverflow.com/a/32851198/755393
    romanize(number){
        const romanTable = { M: 1000, CM: 900, D: 500, CD: 400, C: 100, XC: 90, L: 50, XL: 40, X: 10, IX: 9, V: 5, IV: 4, I: 1 };
        let result       = '';

        for (let romanTableIndex in romanTable) {
            while (number >= romanTable[romanTableIndex]) {
                result += romanTableIndex;
                number -= romanTable[romanTableIndex];
            }
        }

        return result;
    }
}
