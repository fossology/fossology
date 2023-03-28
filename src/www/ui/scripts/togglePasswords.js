/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Simran Nigam

 SPDX-License-Identifier: GPL-2.0-only
*/
const togglePassword = document.querySelector('#togglePasswords');
// const togglePassword = document.querySelector('#togglePasswords');
if (togglePassword) {
  const password = document.querySelector('#passcheck');
  togglePassword.addEventListener('click', function (e) {
    // toggle the type attribute
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    // toggle the eye slash icon
    this.classList.toggle('fa-eye-slash');
  });
} else  {
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#pwd');
  togglePassword.addEventListener('click', function (e) {
    // toggle the type attribute
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    // toggle the eye slash icon
    this.classList.toggle('fa-eye-slash');
  });
  }
  