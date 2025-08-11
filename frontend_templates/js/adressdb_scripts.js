(function ($) {
  // Check if Google Maps API is loaded
  function checkGoogleMapsAPI() {
    if (typeof google === "undefined" || typeof google.maps === "undefined") {
      console.error("Google Maps API failed to load");
      return false;
    }
    return true;
  }

  // Show error message when map fails to load
  function showMapError($el, message) {
    $el.html(
      '<div class="cwd_overlay-w"><p class="cwd_noresult_map">' +
        message +
        "</p></div>"
    );
    $el.addClass("acf-map-error");
  }

  /*
   *  new_map
   *
   *  This function will render a Google Map onto the selected jQuery element
   *
   *  @type	function
   *  @date	8/11/2013
   *  @since	4.3.0
   *
   *  @param	$el (jQuery element)
   *  @return	n/a
   */
  function new_map($el) {
    // Check if Google Maps API is available
    if (!checkGoogleMapsAPI()) {
      showMapError($el, "Google Maps konnte nicht geladen werden");
      return null;
    }

    // var
    var $markers = $el.find(".marker");

    // Check if we have markers
    if ($markers.length === 0) {
      showMapError($el, "Keine Standorte zum Anzeigen verfügbar");
      return null;
    }

    // vars
    var args = {
      zoom: 16,
      center: new google.maps.LatLng(54.0924, 12.0991), // Rostock as default center
      mapTypeId: google.maps.MapTypeId.ROADMAP,
      mapTypeControl: true,
      streetViewControl: true,
      fullscreenControl: true,
      zoomControl: true,
    };

    try {
      // create map
      var map = new google.maps.Map($el[0], args);

      // add a markers reference
      map.markers = [];

      // add markers
      $markers.each(function () {
        add_marker($(this), map);
      });

      // center map
      center_map(map);

      // return
      return map;
    } catch (error) {
      console.error("Error creating map:", error);
      showMapError($el, "Fehler beim Erstellen der Karte");
      return null;
    }
  }

  /*
   *  add_marker
   *
   *  This function will add a marker to the selected Google Map
   *
   *  @type	function
   *  @date	8/11/2013
   *  @since	4.3.0
   *
   *  @param	$marker (jQuery element)
   *  @param	map (Google Map object)
   *  @return	n/a
   */
  function add_marker($marker, map) {
    try {
      // Get coordinates
      var lat = parseFloat($marker.attr("data-lat"));
      var lng = parseFloat($marker.attr("data-lng"));
      var title = $marker.attr("data-title");

      // Validate coordinates
      if (isNaN(lat) || isNaN(lng)) {
        console.warn("Invalid coordinates for marker:", title, lat, lng);
        return;
      }

      // Check if coordinates are in valid range
      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        console.warn("Coordinates out of range for marker:", title, lat, lng);
        return;
      }

      var latlng = new google.maps.LatLng(lat, lng);

      // create marker
      var marker = new google.maps.Marker({
        position: latlng,
        map: map,
        title: title,
        animation: google.maps.Animation.DROP,
      });

      // add to array
      map.markers.push(marker);

      // if marker contains HTML, add it to an infoWindow
      if ($marker.html()) {
        // create info window
        var infowindow = new google.maps.InfoWindow({
          content: $marker.html(),
          maxWidth: 300,
        });

        // show info window when marker is clicked
        google.maps.event.addListener(marker, "click", function () {
          infowindow.open(map, marker);
        });
      }
    } catch (error) {
      console.error("Error adding marker:", error, $marker);
    }
  }

  /*
   *  center_map
   *
   *  This function will center the map, showing all markers attached to this map
   *
   *  @type	function
   *  @date	8/11/2013
   *  @since	4.3.0
   *
   *  @param	map (Google Map object)
   *  @return	n/a
   */
  function center_map(map) {
    if (!map || !map.markers || map.markers.length === 0) {
      return;
    }

    try {
      // vars
      var bounds = new google.maps.LatLngBounds();

      // loop through all markers and create bounds
      $.each(map.markers, function (i, marker) {
        var latlng = new google.maps.LatLng(
          marker.position.lat(),
          marker.position.lng()
        );
        bounds.extend(latlng);
      });

      // only 1 marker?
      if (map.markers.length == 1) {
        // set center of map
        map.setCenter(bounds.getCenter());
        map.setZoom(16);
      } else {
        // fit to bounds
        map.fitBounds(bounds);

        // Ensure minimum zoom level
        google.maps.event.addListenerOnce(map, "bounds_changed", function () {
          if (map.getZoom() > 18) {
            map.setZoom(18);
          }
        });
      }
    } catch (error) {
      console.error("Error centering map:", error);
    }
  }

  // Handle Google Maps API loading errors
  function handleGoogleMapsError() {
    console.error("Google Maps API failed to load");
    $(".acf-map").each(function () {
      if (!$(this).hasClass("acf-map-error")) {
        showMapError($(this), "Google Maps API konnte nicht geladen werden");
      }
    });
  }

  // Add global error handler for Google Maps
  window.gm_authFailure = function () {
    console.error("Google Maps API authentication failed");
    $(".acf-map").each(function () {
      if (!$(this).hasClass("acf-map-error")) {
        showMapError(
          $(this),
          "Google Maps API Authentifizierung fehlgeschlagen"
        );
      }
    });
  };

  /*
   *  document ready
   *
   *  This function will render each map when the document is ready (page has loaded)
   *
   *  @type	function
   *  @date	8/11/2013
   *  @since	5.0.0
   *
   *  @param	n/a
   *  @return	n/a
   */
  // global var
  var map = null;

  $(document).ready(function () {
    // Add some delay to ensure Google Maps API is fully loaded
    setTimeout(function () {
      $(".acf-map").each(function () {
        // Skip if this map already has an error
        if ($(this).hasClass("acf-map-error")) {
          return;
        }

        // Skip placeholder maps
        if ($(this).hasClass("acf-map-placeholder")) {
          return;
        }

        // create map
        map = new_map($(this));
      });
    }, 100);

    // Handle form submission
    $("#cwd_main_search").on("submit", function () {
      // Show loading indicator
      $("#cwd_submit_search").val("Suche läuft...").prop("disabled", true);
    });

    // Handle filter form submission
    $("#cwd_service_filter_form").on("submit", function () {
      $("#cwd_submit_filter")
        .val("Filter wird angewendet...")
        .prop("disabled", true);
    });

    // Add error handling for images
    $(".cwd_icon_info").on("error", function () {
      $(this).hide();
    });
  });

  // Handle window resize
  $(window).on("resize", function () {
    if (map && typeof google !== "undefined" && google.maps) {
      setTimeout(function () {
        google.maps.event.trigger(map, "resize");
        if (map.markers && map.markers.length > 0) {
          center_map(map);
        }
      }, 100);
    }
  });
})(jQuery);
