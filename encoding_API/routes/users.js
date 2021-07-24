const express = require('express');
const mongoose = require('mongoose');
const {User, validateUser} = require('../models/userModel');
const router = express.Router();

router.post('/', async (req, res) => {
    // first time, validating the data sent by user
    const { error } = validateUser(req.body);
    if (error)
        return res.status(400).json(error.details[0].message);

    // verify if user exist in db
    let user = await User.findOne({ email: req.body.email });
    if (user)
        return res.status(400).json({message: 'User already exist in DB !'})
    
    user = new User({
        name : req.body.name,
        email: req.body.email,
        password: req.body.password
    })

    // finally saving user in Db
    await user.save();
    res.json(user);
});

module.exports = router;
