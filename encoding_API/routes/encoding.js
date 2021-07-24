const spawn = require('child_process').spawn;
const fs = require ('fs');

function resizeVideo(video, sourcePath, outputPath, quality) {
    return new Promise((resolve, reject) => {
        const ffmpeg = spawn('ffmpeg', ['-i', `${sourcePath}/${video}.mp4`, '-codec:v', 'libx264',
                                                    '-profile:v', 'main', '-preset', 'slow', '-b:v',
                                                     '400k', '-maxrate', '400k', '-bufsize', '800k',
                                                      '-vf', `scale=-2:${quality}`, '-threads', '0', 
                                                      '-b:a', '128k', `${outputPath}/${video}_${quality}.mp4`, '-y']);
        ffmpeg.stderr.on('data', (data) => {
            console.log(`${data}`);
        });
        ffmpeg.on('close', (code) => {
            if (code !== 0) {
                reject('Error (code) : ', code)
            }
            resolve();
        });
    });
}

function processVideos(videoName, sourcePath, outputPath) {
    if (videoName) {
        try {
            return Promise.all([resizeVideo(videoName, sourcePath, outputPath,  720), 
                                resizeVideo(videoName, sourcePath, outputPath, 480), 
                                resizeVideo(videoName, sourcePath, outputPath, 360)])
        } catch (error) {
            console.log('Error while encoding video !');
            return new Error('Error while encoding video !');
        }
    }
}


module.exports = processVideos;