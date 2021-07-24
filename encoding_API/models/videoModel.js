const mongoose = require("mongoose");

const videoSchema = new mongoose.Schema({
    title: String,
    author: String,
    date: {type: Date, default: Date.now} // upload date
})

const Video = mongoose.model('Video', videoSchema);



module.exports = Video;