var mm_gmap = {
  map: {},
  marker: {},
  addressField: {}
};

var $j = jQuery.noConflict();

mm_gmap.init = function(id,defaultGeoLoc) {
  mapContainerId = "map_canvas_"+id;
  $j("#"+id).after("<div id='"+mapContainerId+"' style='width: 500px; height: 300px; margin:5px 0 7px;'>Loading map, please wait...</div>");
  $j("#"+mapContainerId).after("<input type='text' id='search_address_" + id + "' value=''><input type='button' onclick='mm_gmap.geoSearch(" + '"' + mapContainerId + '"' + ");' value='Search'>");
  $j("#"+mapContainerId).data("googlemap_tvId",id);
  $j("#"+mapContainerId).data("googlemap_defaultGeoLocation",defaultGeoLoc);
  mm_gmap.addressField[id] = document.getElementById('search_address_'+id);
  mm_gmap.draw(mapContainerId);
};

mm_gmap.draw = function(mapContainerId) {
  let tvId = $j("#"+mapContainerId).data("googlemap_tvId");
  let defaultGeoLoc = $j("#"+mapContainerId).data("googlemap_defaultGeoLocation");
  let geoLoc;
  let initOverlay = false;

  if ($j("#"+tvId).val() != '') {		// TV contains a value already?
    geoLoc = $j("#"+tvId).val().split(',');
    initOverlay = true;
  }else {
    geoLoc = (defaultGeoLoc != '')? defaultGeoLoc.split(',') : [35.6585805, 139.74543289999997];	// get default from mm_rules, otherwise head to berlin
  }

  let center = new google.maps.LatLng(geoLoc[0], geoLoc[1]);
  let mapOptions = {
    disableDoubleClickZoom:true,
    center: center,
    zoom: 16,
    mapTypeId: google.maps.MapTypeId.ROADMAP
  };
  mm_gmap.map[tvId] = new google.maps.Map(document.getElementById(mapContainerId),mapOptions);
  geocoder = new google.maps.Geocoder();

  if (initOverlay) {
    mm_gmap.addMarker(center,tvId); 
  }

  google.maps.event.addListener(mm_gmap.map[tvId], 'dblclick', function(point) {
    mm_gmap.addMarker(point.latLng,tvId);
  });
};

mm_gmap.addMarker = function(myLatLng,tvId){
	if (mm_gmap.marker[tvId]) {
		mm_gmap.marker[tvId].setMap(null);
	}
	mm_gmap.marker[tvId] = new google.maps.Marker({
		position: myLatLng,
		map: mm_gmap.map[tvId],
		draggable:true
	});
	mm_gmap.map[tvId].setCenter(myLatLng);
	$j("#"+tvId).val(myLatLng.lat() + ',' + myLatLng.lng());

	// drag marker listeners
	google.maps.event.addListener(mm_gmap.marker[tvId], "dragend", function(point) { 
		$j("#"+tvId).val(point.latLng.lat() + ',' + point.latLng.lng());
	});
};

mm_gmap.geoSearch = function(mapContainerId) {
  let tvId = $j("#"+mapContainerId).data("googlemap_tvId");
  geocoder.geocode( {'address': mm_gmap.addressField[tvId].value}, function(results, status) { 
    if (status == google.maps.GeocoderStatus.OK) { 
      let loc = results[0].geometry.location;
      mm_gmap.addMarker(loc,tvId);
    } else {
      alert("Not found: " + status); 
    }
  });
};

