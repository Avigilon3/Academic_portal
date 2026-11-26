(function () {
  'use strict';

  var form = document.querySelector('[data-cv-form]');
  if (!form) {
    return;
  }

  function linesFrom(name) {
    var field = form.querySelector('[name="' + name + '"]');
    return field ? field.value.split(/\r\n|\r|\n/).map(function (line) {
      return line.trim();
    }).filter(Boolean) : [];
  }

  function replaceList(container, values) {
    if (!container) {
      return;
    }
    while (container.firstChild) {
      container.removeChild(container.firstChild);
    }
    values.forEach(function (value) {
      var item = document.createElement('li');
      item.textContent = value;
      container.appendChild(item);
    });
  }

  function updatePreview() {
    var summary = form.querySelector('[name="professional_summary"]');
    var summaryOutput = document.querySelector('[data-preview-summary-output]');
    if (summary && summaryOutput) {
      summaryOutput.textContent = summary.value;
    }

    replaceList(document.querySelector('[data-preview-skills]'), linesFrom('skills'));
    replaceList(document.querySelector('[data-preview-links]'), linesFrom('portfolio_links'));

    var experienceContainer = document.querySelector('[data-preview-experience]');
    if (experienceContainer) {
      while (experienceContainer.firstChild) {
        experienceContainer.removeChild(experienceContainer.firstChild);
      }
      linesFrom('experience').forEach(function (value) {
        var paragraph = document.createElement('p');
        paragraph.textContent = value;
        experienceContainer.appendChild(paragraph);
      });
    }
  }

  form.addEventListener('input', updatePreview);
}());
