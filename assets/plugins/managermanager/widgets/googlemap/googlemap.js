var $j = jQuery.noConflict();
var map = new Object();
var marker = new Object();
var addressField = new Object();

function googlemap(id,defaultGeoLoc) {
	mapContainerId = "map_canvas_"+id;
	// google maps js is loaded async, so we (loop) wait for it
	if (!google || !google.maps) {
		setTimeout('googlemap("'+id+'","'+defaultGeoLoc+'");', 200);
	}
	else {
		$j("#"+id).after("<div id='"+mapContainerId+"' style='width: 500px; height: 300px; margin:5px 0 7px;'>Loading map, please wait...</div>");
		$j("#"+mapContainerId).after("<input type='text' id='search_address_" + id + "' value=''><input type='button' onclick='geoSearch(" + '"' + mapContainerId + '"' + ");' value='Search'>");
		$j("#"+mapContainerId).data("googlemap_tvId",id);
		$j("#"+mapContainerId).data("googlemap_defaultGeoLocation",defaultGeoLoc);
		addressField[id] = document.getElementById('search_address_'+id);
		StartGoogleMaps(mapContainerId);
	}
}


function StartGoogleMaps(mapContainerId) {
	
	var tvId = $j("#"+mapContainerId).data("googlemap_tvId");
	var defaultGeoLoc = $j("#"+mapContainerId).data("googlemap_defaultGeoLocation");
	var geoLoc;
	var initOverlay = false;
	
	if ($j("#"+tvId).val() != '') {		// TV contains a value already?
		geoLoc = $j("#"+tvId).val().split(',');
		initOverlay = true;
	}else {
		geoLoc = (defaultGeoLoc != '')? defaultGeoLoc.split(',') : new Array(35.6585805,139.74543289999997);	// get default from mm_rules, otherwise head to berlin
	}

	var center = new google.maps.LatLng(geoLoc[0], geoLoc[1]);
	var mapOptions = {
		disableDoubleClickZoom:true,
		center: center,
		zoom: 16,
		mapTypeId: google.maps.MapTypeId.ROADMAP
 	  };
	map[tvId] = new google.maps.Map(document.getElementById(mapContainerId),mapOptions);
	geocoder = new google.maps.Geocoder();

	if (initOverlay) { addMarker(center,tvId); }

	google.maps.event.addListener(map[tvId], 'dblclick', function(point) {
		addMarker(point.latLng,tvId);
	});
}
	

function addMarker(myLatLng,tvId){
	if(marker[tvId]){marker[tvId].setMap(null);}
	marker[tvId] = new google.maps.Marker({
		position: myLatLng,
		map: map[tvId],
		draggable:true
	});
	map[tvId].setCenter(myLatLng);
	$j("#"+tvId).val(myLatLng.lat() + ',' + myLatLng.lng());

	// drag marker listeners
	google.maps.event.addListener(marker[tvId], "dragend", function(point) { 
		$j("#"+tvId).val(point.latLng.lat() + ',' + point.latLng.lng());
	});
}

function geoSearch(mapContainerId) {
	var tvId = $j("#"+mapContainerId).data("googlemap_tvId");
    geocoder.geocode( {'address': addressField[tvId].value}, function(results, status) { 
            if (status == google.maps.GeocoderStatus.OK) { 
                var loc = results[0].geometry.location;
				addMarker(loc,tvId);
            } else {
                alert("Not found: " + status); 
  }
	});
};

