!(function (e, t) {
	"use strict";
	var i,
		n = e,
		a = document,
		d = a.getElementsByTagName("html")[0],
		o = a.getElementsByTagName("body")[0],
		s = { container: o, autoPlay: !0, mediaAspect: 16 / 9, mediaType: "html5", volume: 0 };
	function r(e, t) {
		var i = t.id();
		if (e.container === o) {
			o.style.height = "auto";
			var s = n.innerHeight > o.clientHeight ? "100%" : "auto";
			o.style.height = d.style.height = s;
		}
		var r = e.container.clientWidth < n.innerWidth ? e.container.clientWidth : n.innerWidth,
			p = e.container.clientHeight < n.innerHeight ? e.container.clientHeight : n.innerHeight,
			l = r / p,
			c = a.getElementById(i),
			h = a.getElementById(i + "_" + e.mediaType + "_api");
		l < e.mediaAspect
			? (t.width(p * e.mediaAspect),
				t.height(p),
				(c.style.top = "0px"),
				(c.style.left = -(p * e.mediaAspect - r) / 2 + "px"),
				(h.style.width = p * e.mediaAspect + "px"),
				(h.style.height = p + "px"))
			: (t.width(r),
				t.height(r / e.mediaAspect),
				(c.style.top = -(r / e.mediaAspect - p) / 2 + "px"),
				(c.style.left = "0px"),
				(c.style.height = r / e.mediaAspect + "px"),
				"html5" === e.mediaType
					? ((h.style.width = h.parentNode.style.width), (h.style.height = "auto"))
					: ((h.style.width = h.parentNode.style.width), (h.style.height = r / e.mediaAspect + "px")));
	}
	(i = function (i) {
		var n = t.mergeOptions(s, i),
			d = this,
			p = a.getElementById(d.id() + "_" + n.mediaType + "_api");
		n.container !== o && "string" == typeof n.container && (n.container = a.getElementById(n.container)),
			(function (e, t) {
				var i = a.getElementById(t.id()),
					n = document.createElement("div");
				n.setAttribute("class", "videojs-background-wrap"), n.appendChild(i), e.container.appendChild(n);
				var d = document.createElement("style"),
					o =
						" .videojs-background-wrap .video-js.vjs-controls-disabled .vjs-poster { position: absolute; top: 0; left:0; width: 100%; height: 100%; background-size: 100%!important; background-size: cover!important; display: block!important; }.videojs-background-wrap .video-js.vjs-has-started .vjs-poster, .videojs-background-wrap .vjs-youtube .vjs-loading-spinner { display: none!important; }";
				d.setAttribute("type", "text/css"),
					document.getElementsByTagName("head")[0].appendChild(d),
					d.styleSheet ? (d.styleSheet.cssText = o) : d.appendChild(document.createTextNode(o));
			})(n, d),
			r(n, d),
			d.volume(n.volume),
			n.autoPlay && d.play(),
			d.on("loadedmetadata", function (e) {
				"html5" === n.mediaType
					? (n.mediaAspect = p.videoWidth / p.videoHeight)
					: void 0 !== p.vjs_getProperty &&
						(n.mediaAspect = p.vjs_getProperty("videoWidth") / p.vjs_getProperty("videoHeight")),
					r(n, d);
			});
		var l = e.onresize;
		e.onresize = function () {
			r(n, d), "function" == typeof l && l();
		};
	}),
		t.plugin("Background", i);
})(window, window.videojs);
