(() => {
  const root = document.getElementById('localGuideMap');
  if (!root || !window.L) return;

  const items = Array.isArray(window.LOCAL_GUIDE_MAP_ITEMS) ? window.LOCAL_GUIDE_MAP_ITEMS : [];
  const start = items.length ? [items[0].lat, items[0].lng] : [22.9734, 78.6569];
  const map = L.map(root, { scrollWheelZoom: false }).setView(start, items.length ? 12 : 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
  }).addTo(map);

  const layer = typeof L.markerClusterGroup === 'function' ? L.markerClusterGroup({showCoverageOnHover:false, spiderfyOnMaxZoom:true}) : L.featureGroup();
  const bounds = [];
  let userLocation = null;
  let userMarker = null;
  let activeRouteLayer = null;

  // Add floating banner for route info
  const infoBanner = document.createElement('div');
  infoBanner.className = 'map-route-card';
  infoBanner.style.cssText = 'display:none;position:absolute;top:16px;right:16px;z-index:1000;background:#ffffff;border:2px solid #146b60;border-radius:12px;padding:12px 18px;box-shadow:0 8px 24px rgba(0,0,0,0.15);font-family:system-ui,sans-serif;max-width:300px;';
  root.parentElement.style.position = 'relative';
  root.parentElement.appendChild(infoBanner);

  // Add Locate Me Control Button on top-left of the map
  const LocateControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: function() {
      const btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control');
      btn.style.cssText = 'background:#ffffff;border:none;cursor:pointer;padding:8px 12px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:6px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.2);';
      btn.innerHTML = '<i class="fa-solid fa-crosshairs" style="color:#146b60;"></i> Near Me';
      btn.title = 'Find places near my current location';
      L.DomEvent.disableClickPropagation(btn);
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        locateUser(true);
      });
      return btn;
    }
  });
  map.addControl(new LocateControl());

  function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return (R * c).toFixed(1);
  }

  function locateUser(center = false, callback = null) {
    if (!navigator.geolocation) {
      alert('Geolocation is not supported by your browser.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        userLocation = [pos.coords.latitude, pos.coords.longitude];
        if (userMarker) map.removeLayer(userMarker);
        
        const userIcon = L.divIcon({
          className: 'user-gps-marker',
          html: '<div style="background:#146b60;color:white;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 6px rgba(20,107,96,0.3);"><i class="fa-solid fa-user" style="font-size:12px;"></i></div>',
          iconSize: [28, 28],
          iconAnchor: [14, 14]
        });

        userMarker = L.marker(userLocation, { icon: userIcon }).addTo(map);
        userMarker.bindPopup('<strong>Your Location</strong>');

        if (center) {
          map.setView(userLocation, 14);
        }
        if (typeof callback === 'function') callback(userLocation);
      },
      (err) => {
        console.warn('Geolocation error:', err.message);
        if (center) alert('Could not access your location. Please check browser permissions.');
      },
      { enableHighAccuracy: true, timeout: 8000 }
    );
  }

  function drawRoute(destLat, destLng, destName) {
    const fetchRoute = (startCoords) => {
      const url = `https://router.project-osrm.org/route/v1/driving/${startCoords[1]},${startCoords[0]};${destLng},${destLat}?overview=full&geometries=geojson`;
      
      infoBanner.style.display = 'block';
      infoBanner.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Calculating route to <strong>${escapeHtml(destName)}</strong>…`;

      fetch(url)
        .then(res => res.json())
        .then(data => {
          if (!data.routes || !data.routes.length) {
            infoBanner.innerHTML = `No road route found. <button id="closeRouteBtn" style="margin-left:8px;border:none;background:none;cursor:pointer;">✕</button>`;
            return;
          }
          const route = data.routes[0];
          const distKm = (route.distance / 1000).toFixed(1);
          const mins = Math.round(route.duration / 60);

          if (activeRouteLayer) map.removeLayer(activeRouteLayer);

          activeRouteLayer = L.geoJSON(route.geometry, {
            style: { color: '#146b60', weight: 6, opacity: 0.85, lineJoin: 'round' }
          }).addTo(map);

          map.fitBounds(activeRouteLayer.getBounds(), { padding: [50, 50] });

          infoBanner.innerHTML = `
            <div style="font-size:0.85rem;color:#557065;margin-bottom:4px;">Directions to ${escapeHtml(destName)}</div>
            <div style="font-size:1.15rem;font-weight:700;color:#10251c;">
              <i class="fa-solid fa-car"></i> ${distKm} km · ~${mins} mins
            </div>
            <div style="margin-top:8px;display:flex;gap:8px;">
              <a href="https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}" target="_blank" rel="noopener" style="font-size:0.85rem;color:#146b60;text-decoration:underline;">Google Maps</a>
              <button id="closeRouteBtn" style="border:none;background:#eef3ef;padding:3px 8px;border-radius:4px;cursor:pointer;font-size:0.8rem;margin-left:auto;">Clear</button>
            </div>
          `;

          document.getElementById('closeRouteBtn').addEventListener('click', () => {
            if (activeRouteLayer) map.removeLayer(activeRouteLayer);
            infoBanner.style.display = 'none';
          });
        })
        .catch(err => {
          console.error(err);
          infoBanner.innerHTML = `Route calculation failed. <button id="closeRouteBtn" style="margin-left:8px;">✕</button>`;
          document.getElementById('closeRouteBtn').addEventListener('click', () => { infoBanner.style.display = 'none'; });
        });
    };

    if (!userLocation) {
      locateUser(false, (coords) => fetchRoute(coords));
    } else {
      fetchRoute(userLocation);
    }
  }

  items.forEach(item => {
    if (!Number.isFinite(item.lat) || !Number.isFinite(item.lng)) return;
    const marker = L.marker([item.lat, item.lng]);

    const popupContent = document.createElement('div');
    popupContent.style.minWidth = '180px';
    popupContent.innerHTML = `
      <strong style="font-size:1.05rem;display:block;margin-bottom:3px;">${escapeHtml(item.name)}</strong>
      <span style="display:inline-block;padding:2px 6px;background:#eef3ef;border-radius:4px;font-size:0.8rem;color:#20332d;margin-bottom:8px;">${escapeHtml(item.category)}</span>
      <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
        <a href="${safeUrl(item.url)}" style="color:#146b60;font-weight:600;font-size:0.9rem;">View place profile →</a>
        <button class="in-app-route-btn" style="border:1px solid #146b60;background:#ffffff;color:#146b60;border-radius:6px;padding:5px 8px;font-size:0.85rem;cursor:pointer;font-weight:600;display:flex;align-items:center;justify-content:center;gap:4px;">
          <i class="fa-solid fa-diamond-turn-right"></i> Draw in-app route
        </button>
      </div>
    `;

    popupContent.querySelector('.in-app-route-btn').addEventListener('click', () => {
      marker.closePopup();
      drawRoute(item.lat, item.lng, item.name);
    });

    marker.bindPopup(popupContent);
    layer.addLayer(marker);
    bounds.push([item.lat, item.lng]);
  });

  map.addLayer(layer);
  if (bounds.length > 1) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 14 });

  function escapeHtml(value) { return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function safeUrl(value) { return String(value).replace(/["<>]/g, ''); }
})();
