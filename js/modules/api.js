(function() {
    const client = new window.OCA.FlzPlaner.Shared.api.ApiClient({ appId: 'flzplaner' });

    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.api = {
        request: client.request.bind(client),
        encode: client.encode.bind(client)
    };
})();
