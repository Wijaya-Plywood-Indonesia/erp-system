document.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
        window.Livewire.emit("keydown.enter");
    }
});
