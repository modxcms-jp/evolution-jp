var mm_gmap = {
  map: {},
  marker: {},
  addressField: {}
};

mm_gmap.init = function (id, defaultGeoLoc) {
  const field = document.getElementById(id);
  // 欄が無ければ何もしない
  if (!field) {
    return;
  }
  const mapContainerId = "map_canvas_" + id;
  field.insertAdjacentHTML("afterend", "<div id='" + mapContainerId + "' style='width: 500px; height: 300px; margin:5px 0 7px;'>Loading map, please wait...</div>");
  const container = document.getElementById(mapContainerId);
  container.insertAdjacentHTML("afterend", "<input type='text' id='search_address_" + id + "' value=''><input type='button' onclick='mm_gmap.geoSearch(" + '"' + mapContainerId + '"' + ");' value='Search'>");
  container.dataset.googlemapTvId = id;
  container.dataset.googlemapDefaultGeoLocation = defaultGeoLoc || '';
  mm_gmap.addressField[id] = document.getElementById('search_address_' + id);
  mm_gmap.draw(mapContainerId);
};

mm_gmap.draw = function (mapContainerId) {
  const container = document.getElementById(mapContainerId);
  if (!container) {
    return;
  }
  let tvId = container.dataset.googlemapTvId;
  let defaultGeoLoc = container.dataset.googlemapDefaultGeoLocation;
  let field = document.getElementById(tvId);
  if (!field) {
    return;
  }
  let geoLoc;
  let initOverlay = false;

  if (field.value != '') {        // TV contains a value already?
    geoLoc = field.value.split(',');
    initOverlay = true;
  } else {
    geoLoc = (defaultGeoLoc != '') ? defaultGeoLoc.split(',') : [35.6585805, 139.74543289999997];    // get default from mm_rules, otherwise head to Tokyo Tower
  }

  let center = new google.maps.LatLng(geoLoc[0], geoLoc[1]);
  let mapOptions = {
    disableDoubleClickZoom: true,
    center: center,
    zoom: 16,
    mapTypeId: google.maps.MapTypeId.ROADMAP
  };
  mm_gmap.map[tvId] = new google.maps.Map(container, mapOptions);
  geocoder = new google.maps.Geocoder();

  if (initOverlay) {
    mm_gmap.addMarker(center, tvId);
  }

  google.maps.event.addListener(mm_gmap.map[tvId], 'dblclick', function (point) {
    mm_gmap.addMarker(point.latLng, tvId);
  });
};

mm_gmap.addMarker = function (myLatLng, tvId) {
  // 値を入れても change は起こさない（今までどおり、未保存の扱いにしない）
  const field = document.getElementById(tvId);
  if (mm_gmap.marker[tvId]) {
    mm_gmap.marker[tvId].setMap(null);
  }
  mm_gmap.marker[tvId] = new google.maps.Marker({
    position: myLatLng,
    map: mm_gmap.map[tvId],
    draggable: true
  });
  mm_gmap.map[tvId].setCenter(myLatLng);
  if (field) {
    field.value = myLatLng.lat() + ',' + myLatLng.lng();
  }

  // drag marker listeners
  google.maps.event.addListener(mm_gmap.marker[tvId], "dragend", function (point) {
    if (field) {
      field.value = point.latLng.lat() + ',' + point.latLng.lng();
    }
  });
};

mm_gmap.geoSearch = function (mapContainerId) {
  const container = document.getElementById(mapContainerId);
  if (!container) {
    return;
  }
  let tvId = container.dataset.googlemapTvId;
  geocoder.geocode({ 'address': mm_gmap.addressField[tvId].value }, function (results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      let loc = results[0].geometry.location;
      mm_gmap.addMarker(loc, tvId);
    } else {
      alert("Not found: " + status);
    }
  });
};
