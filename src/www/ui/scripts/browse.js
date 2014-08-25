jQuery.fn.dataTableExt.oSort["hiddenmagic-asc"] = function ( a, b ) {
    var numa = a.match(/[0-9]+/g);
    var numb = b.match(/[0-9]+/g);
    return (numa[0]<numb[0]) ? 1 : (numa[0]>numb[0]) ? -1 : 0;
    for(i=0;i<numa.length;i++){
      if (numb.length<i)
        return 1;
      if (parseInt(numa[i])<parseInt(numb[i]))
        return -1;
      if (parseInt(numa[i])>parseInt(numb[i]))
        return 1;
    }
    if (numb.length>numa.length)
      return -1;
    return 0;
};

jQuery.fn.dataTableExt.oSort["hiddenmagic-desc"] = function ( a, b ) {
  return jQuery.fn.dataTableExt.oSort["hiddenmagic-asc"](b,a);
};

$(document).ready(function() {
        createBrowseTable();
      });
