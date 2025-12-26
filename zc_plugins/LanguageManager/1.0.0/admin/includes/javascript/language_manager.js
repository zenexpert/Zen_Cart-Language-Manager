function filterTable() {
    var input = document.getElementById("searchInput");
    var filter = input.value.toUpperCase();
    var table = document.getElementById("langTable");
    var tr = table.getElementsByTagName("tr");
    for (var i = 1; i < tr.length; i++) {
        var tdKey = tr[i].getElementsByTagName("td")[0];
        var tdOrig = tr[i].getElementsByTagName("td")[1];
        if (tdKey || tdOrig) {
            var txtValueKey = tdKey.textContent || tdKey.innerText;
            var txtValueOrig = tdOrig.textContent || tdOrig.innerText;
            if (txtValueKey.toUpperCase().indexOf(filter) > -1 || txtValueOrig.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}
