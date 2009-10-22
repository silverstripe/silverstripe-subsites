<?php

class FaviconImage extends Image{
	function generateRightImage($gd) {
		$gd->setQuality(90);
		return $gd->resizeByWidth(16);
	}
	
	public function CMSThumbnail() {
		return $this->getFormattedImage('CMSThumbnail');
	}
}

?>