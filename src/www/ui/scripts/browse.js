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
  var myKey = 0;
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
    var myVal = $(this).find("input.hidePriority").val();
    $(".priobucket").each( function(){
      var yourVal = $(this).find("input.hidePriority").val();
      if (myVal>yourVal){
        $(this).find("img").attr("src", "images/dataTable/sort_asc.png");
        //$(this).css("background-image","url(images/dataTable/sort_asc.png)");
      }
      else if(myVal<yourVal){
        $(this).find("img").attr("src", "images/dataTable/sort_desc.png");
      }
    } );
  } );
  
  /*
  $(".priobucket").mouseup( function() {
    var yourKey = $(this).find("input.hideUploadid").val();
    if(myKey>0 && (yourKey != myKey)){
      window.location.href = window.location.href+'&move='+myKey+'&beyond='+yourKey;
    }
    else
    {
      $(".priobucket").each( function(){
        $(this).find("img").attr("src", "images/dataTable/sort_both.png");
        // $(this).css("background-image","url(images/dataTable/sort_both.png)");
      });
      myKey = 0;
    }
  });
  
  $("body").mouseup( function() {
    $(".priobucket").find("img").attr("src", "images/dataTable/sort_both.png");
    // $(".priobucket").css("background-image","url(images/dataTable/sort_both.png)");
    myKey = 0;
  } );
  */
  
});
