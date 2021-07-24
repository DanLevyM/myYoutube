const express = require('express');
const Joi = require('joi');
const router = express.Router();
const fs = require('fs');
const spawn = require('child_process').spawn;
const basePath = '../userVideos';
const Video = require('../models/videoModel');
const videoEncoder = require('./encoding');
const axios = require('axios');
const shell = require('shelljs');


// videos = [
//     {id: 1, name: 'Montero', url: 'youtube.com/montero'},
//     {id: 2, name: 'In da club', url: 'youtube.com/indaclub'},
//     {id: 3, name: 'dior', url: 'youtube.com/dior'},
//     {id: 4, name: 'Montero', url: 'youtube.com/montero'}
// ]

router.get('/', (req, res) => {
    res.status(201).json(
        videos
    )
    res.end()
});

// globales variables
let videosArray = [];

router.get('/:id', (req, res) => {
    const video = videos.find(c => c.id === parseInt(req.params._id));
    if (!video) {
        res.status(404).send('The video with the given ID was not found !')
    }
    res.status(201).json(
        video
    )
});

// ENCODING ENDPOINT
router.post('/encoding', async (req, res) => {
    // Data validation
    console.log(req.body)
    const result = validePOSTEncoding(req.body);
    // Bad request
    if (result.error) {
        console.log(result.error)
        res.status(400).json({Error: result.error.details[0].message});
        return;
    }

    // check if directory exists
    if (fs.existsSync(req.body.task.videoPath) && 
        fs.existsSync(req.body.task.videoPath + '/' + req.body.task.videoName + '.mp4')) {
        console.log('Directory exists!');

        // create "transcoded" directory 
        shell.mkdir('-p', req.body.task.videoPath + '/transcoded/' + req.body.task.videoName);

        if (fs.existsSync(req.body.task.videoPath + '/transcoded/' + req.body.task.videoName)) {
            console.log('Directory created !');

            const outputPath = req.body.task.videoPath + '/transcoded/' + req.body.task.videoName;

            videoEncoder(req.body.task.videoName, req.body.task.videoPath, outputPath)
            .then((_) => {
                console.log('Video encoded successfully !');

                // Ping Mailer API
                axios.default.post('http://localhost:3002', req.body);
            });

            // Return a response to the API
            res.status(200).json({
                code: 200,
                message: 'Encoding is running !'
            })
        }
        
    } else {
        console.error('Directory not found.');
        // Return a server error response to the API
        res.status(500).json({
            code: 500,
            message: 'Error: Invalid filepath or filename !'
    })
    }
})

// ------ Validators for encoding endpoint ------
function validePOSTEncoding(reqBody) {
    const schema = {
        username: Joi.string().min(3).required(),
        email: Joi.string().min(6).required(),
        task: Joi.object()
                .keys({
                    videoName: Joi.string().min(3).required(),
                    videoPath: Joi.string().min(6).required(),
                    videoURL: Joi.string().min(6).optional(),
                })
                .required()
    };
    
    return result = Joi.validate(reqBody, schema);
}

async function createVideo(title, author) {
    const video = new Video({
        title: title,
        author: author
    });
    try {
        return await video.save();
    } catch (error) {
        console.log('Error while creating video : ', error);
    }
}

async function getVideos () {
    videosArray = await Video.find();
}


// --------- POST Methodes -------

// --- Validators --- 
function validePUTJoi(reqBody) {
    const schema = {
        name: Joi.string().min(3),
        author: Joi.string().min(3),
        _id: Joi.number().required(),
        url: Joi.string().min(10)
    };
    
    return result = Joi.validate(reqBody, schema);
}

function validePOSTJoi(reqBody) {
    const schema = {
        name: Joi.string().min(3).required(),
        author: Joi.string().min(3),
        _id: Joi.string(),
        url: Joi.string().min(5),
    };
    
    return result = Joi.validate(reqBody, schema);
}


router.post('/', async (req, res) => {
    // verifying if the content exist in db
    getVideos();
    const video = videosArray.find(v => v._doc._id.toString() === req.body._id);
    if (video) {
        // Content already exist 
        res.status(400).json({Error: 'Content already exist !' });
        return;
    }
    const result = validePOSTJoi(req.body);
    if (result.error) {
        res.status(400).json({Error: result.error.details[0].message});
        return;
    }

    const payload = createVideo(req.body.name, req.body.author)
    const data = await payload;
    const newVideo = {
        id: data._doc._id,
        name: data._doc.title,
        url: data._doc.url ? data._doc.url : 'youtube.com/' + data._doc.title,
        author: data._doc.author
    }
    videos.push(newVideo);
    // console.log(videos)
    res.status(200).json({result: newVideo});
})


// --------- PUT Methodes -------

router.put('/', (req, res) => {
    // verifying if the content exist in db
    const video = videos.find(v => v.id === parseInt(req.body.id));
    if (!video) {
        // Not found content
        res.status(404).json({Error: 'Not found content' });
    }

    const result = validePUTJoi(req.body);
    // Bad request
    if (result.error) {
        res.status(400).json({Error: result.error.details[0].message});
        return;
    }


    const index = videos.findIndex(e => e.id === parseInt(req.body.id));
    video.id = parseInt(req.body.id);
    video.name = req.body.name && req.body.name !== video.name ? req.body.name : video.name ;
    video.url = req.body.url && req.body.url !== video.url ? req.body.url : video.url ;
    videos[index] = video;
    console.log(videos)

    res.status(201).json({video});
})

// --------- DELETE Methodes -------
router.delete('/:id', (req, res) => {
    // verifying if the content exist in DB
    console.log('hye')
    console.log(parseInt(req.params.id))
    const video = videos.find(v => v.id === parseInt(req.params.id));
    if (!video) {
        // Not found content
        res.status(404).json({Error: 'Not found content' });
    }

    const index = videos.findIndex(v => v.id === parseInt(req.params.id));
    videos.splice(index, 1);
    console.log(videos)
    res.status(200).json({Success: 'Content deleted succefully !'});
});

module.exports = router;