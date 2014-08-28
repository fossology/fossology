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

var myKey = 0;
var myVal = 0;
$(document).ready(function() {
  createBrowseTable();
  $(".priobucket").click( function() {
    yourKey = $(this).find("input.hideUploadid").val();
    if (yourKey==myKey){
      $(".priobucket").each( function(){
        $(this).find("img").attr("src", "images/dataTable/sort_both.png");
      });
      myKey = 0;
      return;
    }
    if(myKey>0){
      window.location.href = window.location.href+'&move='+myKey+'&beyond='+yourKey;
      return;
    }
    myKey = $(this).find("input.hideUploadid").val();
    myVal = $(this).find("input.hidePriority").val();
    $(".priobucket").each( function(){
      var yourVal = $(this).find("input.hidePriority").val();
      if (myVal<yourVal){
        $(this).find("img").attr("src", "images/dataTable/sort_asc.png");
      }
      else if(myVal>yourVal){
        $(this).find("img").attr("src", "images/dataTable/sort_desc.png");
      }
    } );
  } );
  
});
