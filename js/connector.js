function getGaConnectorCookies() {
  const cookies = document.cookie.split("; ");
  const result = {};
  cookies.forEach(cookie => {
    const [key, ...valParts] = cookie.split("=");
    const value = valParts.join("=");
    if (key.startsWith("gaconnector_")) {
      result[key] = value;
    }
  });
  return result;
}

function setGaconnectorHiddenFields() {
  // Get all GA connector cookies
  const gaFields = getGaConnectorCookies();
  
  // Find all fields with ga-cookie-pair class
  const gaConnectorFields = document.querySelectorAll('input.ga-cookie-pair');
  
  // Update each field with the corresponding cookie value
  gaConnectorFields.forEach(field => {
    const cookieName = field.getAttribute('data-cookie-name');
    if (cookieName) {
      // Remove the __c suffix if present to get the base cookie name
      const baseCookieName = cookieName.replace(/__c$/, '');
      
      if (gaFields[baseCookieName]) {
        field.value = gaFields[baseCookieName];
        field.setAttribute('data-gaconnector-tracked', 'true');
      }
    }
  });
  
  // Legacy support for other field formats
  for (const fieldName in gaFields) {
    const value = gaFields[fieldName];
    const selectors = 'form input[name="' + fieldName + '"], form input#' + fieldName + ', form input[id^="field_' + fieldName + '"], form input[id^="field_' + fieldName.toLowerCase() + '"], form input[name="' + fieldName.toLowerCase() + '"], form input#' + fieldName.toLowerCase() + ', input[value="gaconnector_' + fieldName + '"]';
    selectors += ', form textarea[name="'+fieldName+'"], form textarea#'+fieldName+', form textarea#field_'+fieldName + ', form textarea[name="'+fieldName.toLowerCase()+'"], form textarea#'+fieldName.toLowerCase()+', form textarea#field_'+fieldName.toLowerCase()+', form textarea.'+fieldName+', form textarea[name="param['+fieldName+']"]'+", form textarea[id^='field_"+fieldName+"']";
    
    const inputs = document.querySelectorAll(selectors);
    if (inputs === null) {
      continue;
    } else if (typeof inputs.length === 'undefined') {
      inputs.value = value;
      jQuery(inputs).trigger('change');
    } else {
      for (let i = 0; i < inputs.length; i++) {
        inputs[i].value = value;
        jQuery(inputs).trigger('change');
      }
    }
  }
}

// Run initially and then periodically to ensure form fields are populated
setGaconnectorHiddenFields();
setInterval(setGaconnectorHiddenFields, 1000); 