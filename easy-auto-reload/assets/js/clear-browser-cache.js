(function () {
    let process_scripts = false;
    const rep = /.*\?.*/;
    const links = document.getElementsByTagName('link');
    const images = document.getElementsByTagName('img');
    const scripts = document.getElementsByTagName('script');
    const values = document.getElementsByName('clear-browser-cache');

    if (values.length > 0) {
        for (let i = 0; i < values.length; i++) {
            if (values[i].value === "true") {
                process_scripts = true;
                break;
            }
        }
    }

    const updateUrl = (element, attr) => {
        if (element[attr]) {
            let url = new URL(element[attr], window.location.origin);
            url.searchParams.set('t', Date.now());
            element[attr] = url.toString();
        }
    };

    for (let i = 0; i < links.length; i++) {
        updateUrl(links[i], 'href');
    }

    for (let i = 0; i < images.length; i++) {
        updateUrl(images[i], 'src');
    }

    if (process_scripts) {
        for (let i = 0; i < scripts.length; i++) {
            updateUrl(scripts[i], 'src');
        }
    }
})();