
var FileState = {
    fields: {
        /* FieldName: {} */
    }
};

function initFileUpload() {
    // set up handlers for files if they exist
    let fileFields = document.querySelectorAll('input.uploadfield');
    fileFields.forEach(function (fileField) {
        let fieldInfo = JSON.parse(fileField.getAttribute('data-schema'));
        if (!fieldInfo || !fieldInfo.data.createFileEndpoint) {
            console.error("Missing file upload details");
            return;
        }

        let fieldState = JSON.parse(fileField.getAttribute('data-state'));
        if (fieldState && fieldState.data && fieldState.data.files && fieldState.data.files.length) {
            var myFiles = [];
            for (var i = 0; i < fieldState.data.files.length; i++) {
                var data = fieldState.data.files[i];
                var entry = {
                    label: data.title,
                    thumb: data.thumbnail,
                    id: data.id
                };
                myFiles.push(entry);
            }
            FileState.fields[fieldState.name] = {
                id: fieldState.id,
                name: fieldState.name,
                files: myFiles
            };
        }

        let uploadUrl = fieldInfo.data.createFileEndpoint.url;
        let isDraft = uploadUrl.indexOf('stage=Stage') > 0;

        fileField.addEventListener('change', function () {
            let securityId = document.querySelector('input[name="SecurityID"]').value;
            if (this.files.length > 0) {
                for (let fi = 0, fl = this.files.length; fi < fl; fi++) {
                    (function (fileObject) {
                        // add a 'pending' item to the file state
                        var current = FileState.fields[fieldState.name];
                        var addIndex = 0;
                        if (current) {
                            addIndex = current.files.length;
                            current.files[addIndex] = {
                                id: 0,
                                label: fileObject.name,
                                percent: 1
                            };
                        }
                        renderFiles();
                        var data = new FormData();
                        data.append('SecurityID', securityId);
                        if (isDraft) {
                            data.append('stage', 'Stage');
                        }

                        var request = new XMLHttpRequest();
                        // File selected by the user
                        // In case of multiple files append each of them
                        data.append('Upload', fileObject);

                        // AJAX request finished
                        request.addEventListener('load', function (e) {
                            // request.response will hold the response from the server
                            if (request.status == 200 && request.response && request.response.length) {
                                // store some data
                                request.response.forEach(function (newFile) {
                                    var fileData = {
                                        id: newFile.id,
                                        label: newFile.title,
                                        thumb: newFile.smallThumbnail ? newFile.smallThumbnail : newFile.thumbnail
                                    }
                                    var current = FileState.fields[fieldState.name];
                                    if (current) {
                                        current.files[addIndex] = fileData;
                                    }
                                });

                                renderFiles();
                            }
                        });

                        // Upload progress on request.upload
                        request.upload.addEventListener('progress', function (e) {
                            var percent_complete = (e.loaded / e.total) * 100;
                            // Percentage of upload completed
                            current.files[addIndex].percent = percent_complete.toFixed(0);
                            renderFiles();
                        });

                        // If server is sending a JSON response then set JSON response type
                        request.responseType = 'json';

                        // Send POST request to the server side script
                        request.open('post', uploadUrl);
                        request.send(data);
                    })(this.files[fi]);

                }
            }
        })
    });

    renderFiles();
}

function renderFiles() {
    if (FileState.fields) {
        for (var fieldName in FileState.fields) {
            var fileData = FileState.fields[fieldName];
            // get the elem, create the hidden fields and display fields
            var field = document.getElementById(fileData.id + '_Holder');
            if (!field) {
                return;
            }

            var placeholder = field.querySelector('.entwine-placeholder');
            if (!placeholder) {
                return;
            }

            while (placeholder.firstChild) {
                placeholder.removeChild(placeholder.firstChild);
            }

            // iterate the files now
            fileData.files.forEach(function (thisFile) {
                var holder = document.createElement('div');
                holder.class = 'FileUpload__ListItem';

                var label = document.createElement('span');
                label.className = 'FileUpload__ListItem__Label';
                label.innerHTML = thisFile.label + (thisFile.percent ? ' - ' + thisFile.percent + '%' : '');

                var thumb = null;
                if (thisFile.thumb) {
                    var thumb = document.createElement('img');
                    thumb.src = thisFile.thumb;
                    thumb.setAttribute('width', '32');
                } else {
                    var thumb = document.createElement('svg');
                    thumb.setAttribute('viewBox', '0 0 8 8');
                    thumb.innerHTML = '<path d="M0 0v1h8v-1h-8zm4 2l-3 3h2v3h2v-3h2l-3-3z"></path>';
                }
                thumb.className = 'FileUpload__ListItem__Thumb';

                // add in our hidden fields, then list each item
                var input = document.createElement('input');
                input.setAttribute('type', 'hidden');
                input.setAttribute('name', fileData.name + '[Files][]');
                input.setAttribute('value', thisFile.id);

                holder.appendChild(thumb);
                holder.appendChild(label);
                holder.appendChild(input);

                placeholder.appendChild(holder);
            });
        }
    }
}
