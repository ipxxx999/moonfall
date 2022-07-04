var USER_ID = '238003';
function devtoolIsOpening() {
    console.clear();
    let before = new Date().getTime();
    debugger;
    let after = new Date().getTime();
    if (after - before > 200) {
        document.write(" No abra Herramientas para desarrolladores. ");
        window.location.replace("error_altared.mp4");
    }
    setTimeout(devtoolIsOpening, 100);
}
devtoolIsOpening();