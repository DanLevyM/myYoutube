const mongoose = require('mongoose');
const Joi = require('joi');

const User = mongoose.model('User', new mongoose.Schema({
    name: {
        type: String,
        require: true,
        minlength: 3,
        maxLength: 50
    },
    email: {
        type: String,
        require: true,
        minlength: 10,
        maxLength: 255,
        unique: true

    },
    password: {
        type: String,
        require: true,
        minlength: 6,
        maxLength: 1024
    }
}))

function validateUser(user) {
    const schema = {
        name: Joi.string().min(3).max(50).required(),
        email: Joi.string().min(10).max(255).required().email(),
        password: Joi.string().min(6).max(1024).required()
    }

    return Joi.validate(user, schema);
}

module.exports.User = User;
module.exports.validateUser = validateUser;