const express = require('express');
const mongoose = require('mongoose');

const videos = require('./routes/videos');
const homePage = require('./routes/home');
const users = require('./routes/users');

const app = express();

// connect to the MongoDB database
mongoose.connect('mongodb://localhost/youtube_clone')
    .then(() => {
        // success
        console.log('Connected to mongodb !')
    }).catch(e => {
        // failed to connect to the db
        console.log(e);
    })


app.use(express.json());
app.use(express.urlencoded({extended: true}))

app.use('', homePage);
app.use('/videos', videos);
app.use('/api/users', users);

// PORT to use for our server
const port = 3005
// Start server and listen to the env or 3005 port 
app.listen(port, () => {
    console.log('Listening on port 3005 ...');
})
