{{flutter_js}}
{{flutter_build_config}}

_flutter.loader.load({
  onEntrypointLoaded: async function(engineInitializer) {
    // Use HTML renderer: avoids CanvasKit's fetch() CORS requirement.
    // CanvasKit uses WebAssembly + fetch() for images which needs CORS preflight.
    // HTML renderer uses <img> tags — cross-origin images load without CORS.
    const appRunner = await engineInitializer.initializeEngine({
      renderer: "html",
    });
    await appRunner.runApp();
  },
});
