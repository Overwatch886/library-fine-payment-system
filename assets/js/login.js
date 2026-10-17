const userTypeInputs = document.querySelectorAll("input[name='user_type']");
const usernameLabel = document.querySelector("label[for='username']");
const usernameInput = document.querySelector("#username");

userTypeInputs.forEach((input) => {
    input.addEventListener("change", () => {
        userTypeInputs.forEach((item) => {
            item.closest(".user-type").classList.remove("active");
        });

        input.closest(".user-type").classList.add("active");

        const isAdministrator = input.value === "administrator";

        usernameLabel.textContent = isAdministrator
            ? "Administrator Username"
            : "Matric Number";

        usernameInput.placeholder = isAdministrator
            ? "Enter your username"
            : "Enter your matric number";
    });
});