(function() {
    const client = new window.LocalBase.api.ApiClient({ appId: 'flzplaner' });

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.api = {
        request: client.request.bind(client),
        encode: client.encode.bind(client)
    };
})();
