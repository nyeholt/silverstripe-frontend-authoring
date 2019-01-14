if (!window.jQuery) {
    console.log("Some features have been disabled as jQuery was not found");
}

const TIMEOUT = 45;

var form = document.getElementById('Form_AuthoringForm');
if (form) {
    var changed_form = false;

    var handleChange = function (e) {
        changed_form = true;
    }

    form.addEventListener('click', function (e) {
        handleChange();
    });

    form.addEventListener('change', function (e) {
        handleChange();
    })

    var submitButton = document.querySelector("[name=action_saveobject]");
    var originalText = submitButton.nodeName.toUpperCase() == 'INPUT' ? submitButton.value : submitButton.innerHTML;

    function saveForm() {
        if (submitButton) {
            if (submitButton.nodeName.toUpperCase() == 'INPUT') {
                submitButton.value = "Saving...";
            } else {
                submitButton.innerHTML = "Saving...";
            }
            submitButton.setAttribute('disabled', 'disabled');
        }

        const w = wretch().content("application/x-www-form-urlencoded");
        const response = w.url(form.getAttribute('action'))
            .body(serialize(form) + '&action_saveobject=Save&ajax=1')
            .headers({ 'Accept': 'application/json' })
            .post();

        response.json(function (data) {
            let errors = document.getElementsByClassName('FormError');
            for (let i = 0; i < errors.length; i++) {
                errors[i].remove();
            }

            if (!data.success && data.length > 0) {
                // we might be errors
                for (var i = 0; i < data.length; i++) {
                    // try and find the holder, and add an error div
                    let holderName = 'Form_AuthoringForm_' + data[i].fieldName + '_Holder';
                    let fieldElem = document.getElementById(holderName);
                    if (fieldElem) {
                        let errorMessage = document.createElement('div');
                        errorMessage.className = 'FormError ' + data[i]['messageType'];
                        errorMessage.innerHTML = data[i].message;
                        fieldElem.appendChild(errorMessage);
                    }
                }
            } else {

            }
            if (submitButton.nodeName.toUpperCase() == 'INPUT') {
                submitButton.value = originalText;
            } else {
                submitButton.innerHTML = originalText;
            }
            submitButton.removeAttribute('disabled');
            changed_form = false;
        }).catch(function (err) {
            if (submitButton.nodeName.toUpperCase() == 'INPUT') {
                submitButton.value = 'Save failed';
            } else {
                submitButton.innerHTML = 'Save failed';
            }
            submitButton.removeAttribute('disabled');
        });

        return response;
    }

    if (submitButton) {
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();
            saveForm();
            return false;
        });
    }

    form.addEventListener('submit', function (e) {
        changed_form = false;
    })


    window.addEventListener('beforeunload', function (e) {
        if (changed_form && !confirm("You have unsaved changes, are you sure?")) {
            e.preventDefault();
            e.returnValue = '';
        }
    })

    var saveTrigger = function () {
        saveForm().res(function () {
            setTimeout(saveTrigger, TIMEOUT * 1000);
        });
    };

    window.setTimeout(saveTrigger, TIMEOUT * 1000);

    initFileUpload();
}






/*!
 * Serialize all form data into a query string
 * (c) 2018 Chris Ferdinandi, MIT License, https://gomakethings.com
 * @param  {Node}   form The form to serialize
 * @return {String}      The serialized form data
 */
var serialize = function (form) {

    // Setup our serialized data
    var serialized = [];

    // Loop through each field in the form
    for (var i = 0; i < form.elements.length; i++) {

        var field = form.elements[i];

        // Don't serialize fields without a name, submits, buttons, file and reset inputs, and disabled fields
        if (!field.name || field.disabled || field.type === 'file' || field.type === 'reset' || field.type === 'submit' || field.type === 'button') continue;

        // If a multi-select, get all selections
        if (field.type === 'select-multiple') {
            for (var n = 0; n < field.options.length; n++) {
                if (!field.options[n].selected) continue;
                serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.options[n].value));
            }
        }

        // Convert field data to a query string
        else if ((field.type !== 'checkbox' && field.type !== 'radio') || field.checked) {
            serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.value));
        }
    }

    return serialized.join('&');

};
