function cloneTemplate(id) {
    const tpl = document.getElementById(id);
    if (!tpl) throw new Error('Template lipsa: ' + id);
    return tpl.content.cloneNode(true);
}

window.cloneTemplate = cloneTemplate;
