const userTypeButtons = document.querySelectorAll(".user-type");
const usernameLabel = document.querySelector("label[for='username']");
const usernameInput = document.querySelector("#username");

userTypeButtons.forEach(button => {
    button.addEventListener("click", () => {
        userTypeButtons.forEach((item) =>{
            item.classList.remove("active");
        });
        button.classList.add("active");

        const isAdministrator = button.dataset.type === "administrator";

        usernameLabel.textContent = isAdministrator
            ? "Administrator Username"
            : "Matric Number";

        usernameInput.placeholder = isAdministrator
            ? "Enter your username"
            : "Enter your matric number";
    });
});