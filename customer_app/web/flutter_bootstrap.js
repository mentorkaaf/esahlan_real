{{flutter_js}}
{{flutter_build_config}}

// Use loadEntrypoint() (not load()) to skip CanvasKit preloading.
// load() triggers a 8-10MB CanvasKit download even when HTML renderer is used.
// loadEntrypoint() loads only main.dart.js then lets us pick the renderer.
_flutter.loader.loadEntrypoint({
  onEntrypointLoaded: async function(engineInitializer) {
    const appRunner = await engineInitializer.initializeEngine({
      renderer: "html",
    });
    await appRunner.runApp();
  },
});
