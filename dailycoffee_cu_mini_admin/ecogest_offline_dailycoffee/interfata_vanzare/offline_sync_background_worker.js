(function () {
    'use strict';

    var busy = false;
    var queue = [];
    var timeoutByType = {
        sync: 14000,
        indicator: 5000,
        catalog: 7000,
        license: 10000
    };

    function sendResult(task, ok, data, error) {
        self.postMessage({
            id: task.id,
            type: task.type,
            ok: ok,
            data: ok ? data : null,
            error: ok ? null : String(error || 'Cererea de fundal a eșuat.')
        });
    }

    function nextTask() {
        if (busy || queue.length === 0) {
            return;
        }

        queue.sort(function (a, b) {
            var priority = { sync: 1, indicator: 2, catalog: 3, license: 4 };
            return (priority[a.type] || 9) - (priority[b.type] || 9);
        });
        run(queue.shift());
    }

    function run(task) {
        busy = true;
        var controller = self.AbortController ? new AbortController() : null;
        var timeout = self.setTimeout(function () {
            if (controller) {
                controller.abort();
            }
        }, timeoutByType[task.type] || 10000);

        self.fetch(task.url, {
            method: task.method || 'GET',
            headers: {
                'Accept': 'application/json'
            },
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.text().then(function (text) {
                var data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (error) {
                    throw new Error('Răspuns JSON invalid de la server.');
                }
                if (!response.ok) {
                    throw new Error(data.message || ('HTTP ' + response.status));
                }
                return data;
            });
        }).then(function (data) {
            sendResult(task, true, data, null);
        }).catch(function (error) {
            sendResult(task, false, null, error && error.message ? error.message : error);
        }).then(function () {
            self.clearTimeout(timeout);
            busy = false;
            nextTask();
        });
    }

    self.onmessage = function (event) {
        var request = event.data || {};
        if (!request.id || !request.type || !request.url) {
            return;
        }

        queue = queue.filter(function (queued) {
            return queued.type !== request.type || request.type === 'sync';
        });
        queue.push(request);
        nextTask();
    };
}());
